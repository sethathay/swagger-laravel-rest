<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Menu\Menu;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class MenuController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TMENU";
    private $awsCognito;
    
    //Initailize constructor of menu controller
    public function __construct(Request $request){
        $this->data     =   $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me       =   new Menu(Route::current()->parameter('tenantId'));
        $this->schema   =   new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Saving new menu resource
     * @date 11-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @return newly created menu json
     */
    public function create($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $aclObj = new ACL($tenantId, $user, $this->schema);
            if(!$aclObj->canAddNew($this->data)){
                return ErrorHelper::getPermissionError();
            }
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Checking for existing of menu resource for given code
                $criteria = new CriteriaOption();
                $mObject = new Menu($tenantId);
                $criteria->where(Menu::FIELD_RECORD_TYPE, $mObject->getRecordType());
                $criteria->where(Menu::FIELD_TENANT, $mObject->getTenantId());
                $criteria->where('code', $this->data['code']);
                $mObject->pushCriteria($criteria);
                $mObject->applyCriteria();
                $findObj = $mObject->readOne();
                if(isset($findObj)){
                    //Validate request with unique name of menu
                    $validator = Validator::make($this->data,[
                        'code'      =>  'unique:menus,code'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                    }
                }
                //Render input and save new menu object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(),$user,"C");
                $outputObj = $this->me->save($obj);
                //Query to get recently added item (Reason: key of menu is code not id)
                $ct = new CriteriaOption();
                $mObj = new Menu($tenantId);
                $ct->where(Menu::FIELD_RECORD_TYPE, $mObj->getRecordType());
                $ct->where(Menu::FIELD_TENANT, $mObj->getTenantId());
                $ct->where('code', $outputObj->getKey());
                $mObj->pushCriteria($ct);
                $mObj->applyCriteria();
                $result = $mObj->readOne();
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager($tenantId);
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $result, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $result->_id;
                $arrOutput = $this->schema->renderOutput($result,$href,$user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager($tenantId);
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Menu', 
                                        $user
                                    );
                $revisionObj->save($eventId,
                                    $this->schema->getAllFields(),
                                    null,
                                    $arrOutput,
                                    'Save',
                                    $this->schema->getPluralName(),
                                    $user);
                return response()->json($arrOutput);
            }else{
                return ErrorHelper::getExceptionError($validateSMS["message"]);
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * Updating a menu resource by id
     * @date 27-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $menuId
     * @return updated menu object json
     */
    public function update($tenantId,$menuId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Getting menu by tenantId and menuId
                $criteria = new CriteriaOption();
                $mObject = new Menu($tenantId);
                $criteria->where(Menu::FIELD_RECORD_TYPE, $mObject->getRecordType());
                $criteria->where(Menu::FIELD_TENANT, $mObject->getTenantId());
                $criteria->whereRaw($mObject->getDefaultFilter($menuId), $this->schema->getAllFields(),$user);
                $mObject->pushCriteria($criteria);
                $mObject->applyCriteria();
                $updatedObj = $mObject->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    if(isset($updatedObj) && $updatedObj->code != $this->data['code']){
                        //Validate request with unique name of menu
                        $validator = Validator::make($this->data,[
                            'code'      =>  'unique:menus,code'
                        ]);
                        if ($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                        }
                    }
                    //Get menu object by id for value-relationship
                    $oldObj = $mObject->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update menu object
                    $obj = $this->schema->renderInput($this->data, $updatedObj, $user, "U");
                    $outputObj = $this->me->save($obj);
                    //Updating value-relationship data
                    $relationshipObj = new RelationshipManager($tenantId);
                    $relationshipObj->update($this->schema->getAllFields(), 
                                            $this->data, 
                                            $oldTargetObj, 
                                            $outputObj, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Render output and return updated resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Menu', 
                                            $user
                                        );
                    $revisionObj->save($eventId,
                                        $this->schema->getAllFields(),
                                        $oldTargetObj,
                                        $result,
                                        'Update',
                                        $this->schema->getPluralName(),
                                        $user);
                    return response()->json($result);
                }else{
                    return ErrorHelper::getNotFoundError("Menu");
                }
            }else{
                return ErrorHelper::getExceptionError($validateSMS["message"]);
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * Removing a menu resource by id
     * @date 29-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $menuId
     * @return -
     */
    public function remove($tenantId,$menuId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Menu::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Menu::FIELD_TENANT, $this->me->getTenantId());
            $criteria->whereRaw($this->me->getDefaultFilter($menuId), $this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $targetObj = $this->me->readOne($this->schema->getAttributes());
            if($targetObj){
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canDelete($targetObj)){
                    return ErrorHelper::getPermissionError();
                }
                $relationshipObj = new RelationshipManager($tenantId);
                if(!$relationshipObj->inUsed($targetObj, $this->schema->getPluralName())){
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Menu', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $menuId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Menu");
                }
            }else{
                return ErrorHelper::getNotFoundError("Menu");
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * Get all menu resources
     * @date 11-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @return list of menus json
     */
    public function readAll($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Menu::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Menu::FIELD_TENANT, $this->me->getTenantId());
            $aclObj = new ACL($tenantId, $user, $this->schema);
            $aclFilters = $aclObj->getFiltersOfACL();
            if(count($aclFilters) > 0 ){
                $criteria->whereRaw($aclFilters,$this->schema->getAllFields(),$user);
            }
            if(isset($this->data['filter'])){
                $criteria->whereRaw($this->data['filter'],$this->schema->getAllFields(),$user);
            }
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $recordSize = $this->me->count();
            if(isset($this->data['offset'])){
                $offset = $criteria->offset($this->data['offset']);
            }else{
                $offset = $criteria->offset();
            }
            if(isset($this->data['limit'])){
                $limit = $criteria->limit($this->data['limit']);
            }else{
                $limit = $criteria->limit();
            }
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/menus";
            if(isset($this->data['expand']) && $this->data['expand'] == 'selections'){
                $ds = $this->me->readAll();
                $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "selections", "TMENUSELECTION", "items");
            }else{
                $ds = $this->me->readAll($this->schema->getAttributes());
                $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
            }
            return response()->json($resultSet);
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
    /**
     * Get a menu resource by id
     * @date 06-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $menuId
     * @return a menu resource in json format
     */
    public function readOne($tenantId, $menuId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Menu::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Menu::FIELD_TENANT, $this->me->getTenantId());
            $criteria->whereRaw($this->me->getDefaultFilter($menuId), $this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $outputObj = $this->me->readOne($this->schema->getAttributes());
            if($outputObj){
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($outputObj)){
                    return ErrorHelper::getPermissionError();
                }
                //Render output and return menu resource in json
                $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/menus/'. $outputObj->_id;
                return response()->json($this->schema->renderOutput($outputObj,$href,$user));
            }else{
                return ErrorHelper::getNotFoundError('Menu');
            }
        }catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $ex){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
}
