<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Menu\MenuSelection;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class MenuSelectionController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TMENUSELECTION";
    private $awsCognito;
    
    //Initailize constructor of menu controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new MenuSelection(Route::current()->parameter('tenantId'),
                                        Route::current()->parameter('menuId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Saving new menu selection resource
     * @date 12-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $menuId
     * @return newly created menu selection json
     */
    public function create($tenantId,$menuId){
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
            $this->data["menuCode"] = $menuId;
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Checking for existing of menu selection resource for given code
                $mObj = $this->me->getMenu($tenantId, $menuId);
                if($mObj){
                    $findObj = $mObj->items()
                            ->where(MenuSelection::FIELD_RECORD_TYPE, $this->me->getRecordType())
                            ->where(MenuSelection::FIELD_TENANT, $tenantId)
                            ->where(MenuSelection::FIELD_MENU, $mObj->code)
                            ->where('code',$this->data["code"])->first();
                    if(isset($findObj)){
                        //Validate request with unique name of version
                        $validator = Validator::make($this->data,[
                            'code' => 'unique:menus,code'
                        ]);
                        if($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                        }
                    }
                    //Render input and save new menu selection object
                    $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                    $outputObj = $this->me->save($obj);
                    //Query to get recently added item (Reason: key of menu selection is code not id)
                    $ct = new CriteriaOption();
                    $mSelObj = new MenuSelection($tenantId, $menuId);
                    $ct->where(MenuSelection::FIELD_RECORD_TYPE, $mSelObj->getRecordType());
                    $ct->where(MenuSelection::FIELD_TENANT, $mSelObj->getTenantId());
                    $ct->where(MenuSelection::FIELD_MENU, $mObj->code);
                    $ct->where('code', $outputObj->getKey());
                    $mSelObj->pushCriteria($ct);
                    $mSelObj->applyCriteria();
                    $result = $mSelObj->readOne();
                    //Saving value-relationship data
                    $relationshipObj = new RelationshipManager($tenantId);
                    $relationshipObj->save($this->schema->getAllFields(), 
                                            $this->data, 
                                            $result, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Render output and return created resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $menuId . "/menuselections/" . $result->_id;
                    $arrOutput = $this->schema->renderOutput($result, $href, $user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Saving New Menu Selection', 
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
     * Updating a menu selection resource by id
     * @date 27-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $menuId
     * @param  string $selectionId
     * @return updated menu selection json
     */
    public function update($tenantId,$menuId,$selectionId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $this->data["menuCode"] = $menuId;
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Checking for existing of menu selection resource for given code
                $mObj = $this->me->getMenu($tenantId, $menuId);
                if($mObj){
                    //Getting menu selection for updating
                    $criteria = new CriteriaOption();
                    $criteria->where(MenuSelection::FIELD_RECORD_TYPE, $this->me->getRecordType());
                    $criteria->where(MenuSelection::FIELD_TENANT, $tenantId);
                    $criteria->where(MenuSelection::FIELD_MENU, $mObj->code);
                    $criteria->whereRaw($this->me->getDefaultFilter($selectionId), $this->schema->getAllFields(), $user);
                    $this->me->pushCriteria($criteria);
                    $this->me->applyCriteria();
                    $updateObj = $this->me->readOne($this->schema->getAttributes());
                    if($updateObj){
                        $aclObj = new ACL($tenantId, $user, $this->schema);
                        if(!$aclObj->canChange($updateObj)){
                            return ErrorHelper::getPermissionError();
                        }
                        $findObj = $mObj->items()
                                        ->where(MenuSelection::FIELD_RECORD_TYPE, $this->me->getRecordType())
                                        ->where(MenuSelection::FIELD_TENANT, $tenantId)
                                        ->where(MenuSelection::FIELD_MENU, $mObj->code)
                                        ->where('code',$this->data["code"])->first();
                        if($updateObj->code != $this->data["code"] && isset($findObj)){
                            //Validate request with unique name of version
                            $validator = Validator::make($this->data,[
                                'code'      =>  'unique:menus,code'
                            ]);
                            if ($validator->fails()){
                                return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                            }
                        }
                        //Get menu selection object by id for value-relationship
                        $oldObj = $this->me->readOne($this->schema->getAttributes());
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $menuId . "/menuselections/" . $oldObj->_id;
                        $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                        //Render input and update menu selection object
                        $saveObj = $this->schema->renderInput($this->data,$updateObj,$user,"U");
                        $outputObj = $this->me->save($saveObj);
                        //Updating value-relationship data
                        $relationshipObj = new RelationshipManager($tenantId);
                        $relationshipObj->update($this->schema->getAllFields(), 
                                                $this->data, 
                                                $oldTargetObj, 
                                                $outputObj, 
                                                $this->schema->getPluralName(), 
                                                $user);
                        //Render output and return updated resource in json
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $menuId . "/menuselections/" . $outputObj->_id;
                        $result = $this->schema->renderOutput($outputObj,$href,$user);
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Modifying Menu Selection', 
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
                        return ErrorHelper::getNotFoundError("Menu Selection");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Menu");
                }
            }else{
                return ErrorHelper::getExceptionError($validateSMS["message"]);
            }
        }
        catch (\InvalidArgumentException $ex){
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
     * Removing a menu selection resource by id
     * @date 29-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $menuId
     * @param  string $selectionId
     * @return -
     */
    public function remove($tenantId,$menuId,$selectionId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $mObj = $this->me->getMenu($tenantId, $menuId);
            if($mObj){
                $criteria = new CriteriaOption();
                $criteria->where(MenuSelection::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(MenuSelection::FIELD_TENANT, $tenantId);
                $criteria->where(MenuSelection::FIELD_MENU, $mObj->code);
                $criteria->whereRaw($this->me->getDefaultFilter($selectionId), $this->schema->getAllFields(), $user);
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
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/menus/'. $menuId . "/menuselections/" . $targetObj->_id;
                        $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                        $relationshipObj->remove($this->schema->getAllFields(), 
                                                    $tObj, 
                                                    $this->schema->getPluralName()
                                                );
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Deleting Menu Selection', 
                                                $user
                                            );
                        $revisionData[] = $revisionObj->getRevision($eventId, 
                                                $this->schema->getPluralName(), 
                                                $selectionId, 
                                                null, 
                                                null, 
                                                null, 
                                                'Delete');
                        $revisionObj->insertData($revisionData, $user);
                        if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                    }else{
                        return ErrorHelper::getInUsedError("Menu Selection");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Menu Selection");
                }
            }else{
                return ErrorHelper::getNotFoundError("Menu");
            }
        }
        catch (\InvalidArgumentException $ex){
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
     * Get all menu selection resources
     * @date 13-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $menuId
     * @return list of menu selection json
     */
    public function readAll($tenantId,$menuId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $mObj = $this->me->getMenu($tenantId, $menuId);
            if($mObj){
                $criteria = new CriteriaOption();
                $criteria->where(MenuSelection::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(MenuSelection::FIELD_TENANT, $tenantId);
                $criteria->where(MenuSelection::FIELD_MENU, $mObj->code);
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
                $ds = $this->me->readAll();
                $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/ " . $tenantId . "/menus/" . $menuId . "/menuselections";
                if(isset($this->data['expand']) && $this->data['expand'] == 'menu'){
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "menu", "TMENU","", $mObj);
                }else{
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
                }
                return response()->json($resultSet);
            }else{
                return ErrorHelper::getNotFoundError("Menu");
            }
        }
        catch (\InvalidArgumentException $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
        catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex){
            return ErrorHelper::getExceptionError($ex->getAwsErrorMessage());
        }
        catch(\Firebase\JWT\ExpiredException $exp){
            return ErrorHelper::getExpiredAccessTokenError();
        }
        catch(\Exception $ex){
            return ErrorHelper::getExceptionError($ex->getMessage());
        }
    }
}