<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ACL\PermissionLibrary\ApplicationPermission;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class ApplicationPermissionController extends Controller{
    
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TAPPLICATIONPERMISSION";
    private $awsCognito;
    
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new ApplicationPermission(Route::current()->parameter('tenantId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Saving new application permission resource
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @return newly created application permission json
     */
    public function create($tenantId, $role){
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
            $this->data["type"] = $this->me->getType();
            $this->data["tenant"] = $this->me->getTenantId();
            $this->data["name"] = $role;
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $roleLibrary = $this->me->getRoleLibrary($tenantId, $role);
                $this->data["name"] = $roleLibrary->name;
                //Render input and save new application permission object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                $outputObj = $this->me->save($obj);
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager($tenantId);
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $outputObj, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/roles/' . $role . '/applicationpermission/'. $outputObj->_id;
                $result = $this->schema->renderOutput($outputObj, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager($tenantId);
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Application Permission', 
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
     * Updating a application permission resource by id
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @param  string $permissionId
     * @return updated application permission json
     */
    public function update($tenantId, $role, $permissionId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            if(isset($this->data["type"])){ unset($this->data["type"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $this->data["name"] = $role;
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $roleLibrary = $this->me->getRoleLibrary($tenantId, $role);
                $this->data["name"] = $roleLibrary->name;
                //Getting role by tenantId and role
                $criteria = new CriteriaOption();
                $appObject = new ApplicationPermission($tenantId);
                $criteria->where(ApplicationPermission::FIELD_RECORD_TYPE, $appObject->getRecordType());
                $criteria->where(ApplicationPermission::FIELD_PERMISSION_TYPE, $appObject->getType());
                $criteria->where(ApplicationPermission::FIELD_TENANT, $appObject->getTenantId());
                $criteria->where('name', $roleLibrary->name);
                $criteria->where('_id', $permissionId);
                $appObject->pushCriteria($criteria);
                $appObject->applyCriteria();
                $updatedObj = $appObject->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    //Get row permission object by id for value-relationship
                    $oldObj = $appObject->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/roles/'. $role . "/applicationpermission/" . $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update row premission object
                    $saveObj = $this->schema->renderInput($this->data, $updatedObj, $user, "U");
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
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/roles/'. $role . "/applicationpermission/" . $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Application Permission', 
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
                    return ErrorHelper::getNotFoundError("Application Permission");
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
     * Removing a application permission resource by id
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @param  string $permissionId
     * @return -
     */
    public function remove($tenantId, $role, $permissionId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $roleLibrary = $this->me->getRoleLibrary($tenantId, $role);
            $criteria = new CriteriaOption();
            $criteria->where(ApplicationPermission::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(ApplicationPermission::FIELD_PERMISSION_TYPE, $this->me->getType());
            $criteria->where(ApplicationPermission::FIELD_TENANT, $this->me->getTenantId());
            $criteria->where('name', $roleLibrary->name);
            $criteria->where('_id', $permissionId);
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
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/roles/'. $role . "/applicationpermission/" . $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Application Permission', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $permissionId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Application Permission");
                }
            }else{
                return ErrorHelper::getNotFoundError("Application Permission");
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
     * Get all application permission resources
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @return list of application permission json
     */
    public function readAll($tenantId, $role){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $roleLibrary = $this->me->getRoleLibrary($tenantId, $role);
            $criteria = new CriteriaOption();
            $criteria->where(ApplicationPermission::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(ApplicationPermission::FIELD_PERMISSION_TYPE, $this->me->getType());
            $criteria->where(ApplicationPermission::FIELD_TENANT, $this->me->getTenantId());
            $criteria->where('name', $roleLibrary->name);
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
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/ " . $tenantId . "/roles/" . $role . "/applicationpermission";
            $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
            return response()->json($resultSet);
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
    /**
     * Getting a application permission resource by id
     * @date 12-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $role
     * @param  string $permissionId
     * @return -
     */
    public function readOne($tenantId, $role, $permissionId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $roleLibrary = $this->me->getRoleLibrary($tenantId, $role);
            $criteria = new CriteriaOption();
            $criteria->where(ApplicationPermission::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(ApplicationPermission::FIELD_PERMISSION_TYPE, $this->me->getType());
            $criteria->where(ApplicationPermission::FIELD_TENANT, $this->me->getTenantId());
            $criteria->where('name', $roleLibrary->name);
            $criteria->where('_id', $permissionId);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $outputObj = $this->me->readOne($this->schema->getAttributes());
            if($outputObj){
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($outputObj)){
                    return ErrorHelper::getPermissionError();
                }
                //Render output and return role resource in json
                $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/roles/'. $role . '/applicationpermission/' . $outputObj->_id;
                return response()->json($this->schema->renderOutput($outputObj, $href, $user));
            }else{
                return ErrorHelper::getNotFoundError('Application Permission');
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
