<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ACL\RoleLibrary\RoleLibrary;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class RoleLibraryController extends Controller{
    
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TROLELIBRARY";
    private $awsCognito;
    
    //Initailize constructor of role library controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new RoleLibrary(Route::current()->parameter('tenantId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Saveing new role library resource
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return newly created role library json
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
                //Checking for existing of role library resource for given name
                $criteria = new CriteriaOption();
                $roleObject = new RoleLibrary($tenantId);
                $criteria->where(RoleLibrary::FIELD_RECORD_TYPE, $roleObject->getRecordType());
                $criteria->where(RoleLibrary::FIELD_TENANT, $roleObject->getTenantId());
                $criteria->where('name', $this->data['name']);
                $roleObject->pushCriteria($criteria);
                $roleObject->applyCriteria();
                $findObj = $roleObject->readOne();
                if(isset($findObj)){
                    //Validate request with unique name of menu
                    $validator = Validator::make($this->data,[
                        'name'      =>  'unique:role_permissions,name'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                    }
                }
                //Render input and save new role object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(),$user,"C");
                $outputObj = $this->me->save($obj);
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager($tenantId);
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $outputObj, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/rolelibraries/'. $outputObj->_id;
                $result = $this->schema->renderOutput($outputObj, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager($tenantId);
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Role Library', 
                                        $user
                                    );
                $revisionObj->save($eventId,
                                    $this->schema->getAllFields(),
                                    null,
                                    $result,
                                    'Save',
                                    $this->schema->getPluralName(),
                                    $user);
                return response()->json($result);
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
     * Updating a role library resource by id/name
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @return updated role library object json
     */
    public function update($tenantId, $role){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            if(isset($this->data["type"])){ unset($this->data["type"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Getting role by tenantId and role
                $criteria = new CriteriaOption();
                $roleObject = new RoleLibrary($tenantId);
                $criteria->where(RoleLibrary::FIELD_RECORD_TYPE, $roleObject->getRecordType());
                $criteria->where(RoleLibrary::FIELD_TENANT, $roleObject->getTenantId());
                $criteria->whereRaw($this->me->getDefaultFilter($role),$this->schema->getAllFields(),$user);
                $roleObject->pushCriteria($criteria);
                $roleObject->applyCriteria();
                $updatedObj = $roleObject->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    if(isset($updatedObj) && $updatedObj->name != $this->data['name']){
                        //Validate request with unique name of role
                        $validator = Validator::make($this->data,[
                            'name'      =>  'unique:role_permissions,name'
                        ]);
                        if ($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                        }
                    }
                    //Get role object by id for value-relationship
                    $oldObj = $roleObject->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/rolelibraries/'. $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update role object
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
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/rolelibraries/'. $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Role Library', 
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
                    return ErrorHelper::getNotFoundError("Role Library");
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
     * Removing a role library resource by id/name
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @return -
     */
    public function remove($tenantId, $role){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(RoleLibrary::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(RoleLibrary::FIELD_TENANT, $this->me->getTenantId());
            $criteria->whereRaw($this->me->getDefaultFilter($role),$this->schema->getAllFields(),$user);
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
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/rolelibraries/'. $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Role Library', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $role, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Role Library");
                }
            }else{
                return ErrorHelper::getNotFoundError("Role Library");
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
     * Get all role library resources
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @return list of roles json
     */
    public function readAll($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(RoleLibrary::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(RoleLibrary::FIELD_TENANT, $this->me->getTenantId());
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
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/rolelibraries";
            $ds = $this->me->readAll($this->schema->getAttributes());
            $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
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
     * Get a role library resource by id/name
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @return a role library resource in json format
     */
    public function readOne($tenantId, $role){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(RoleLibrary::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(RoleLibrary::FIELD_TENANT, $this->me->getTenantId());
            $criteria->whereRaw($this->me->getDefaultFilter($role),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $outputObj = $this->me->readOne($this->schema->getAttributes());
            if($outputObj){
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($outputObj)){
                    return ErrorHelper::getPermissionError();
                }
                //Render output and return role resource in json
                $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/rolelibraries/'. $outputObj->_id;
                return response()->json($this->schema->renderOutput($outputObj,$href,$user));
            }else{
                return ErrorHelper::getNotFoundError('Role Library');
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
