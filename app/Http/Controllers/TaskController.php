<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Project\Task;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class TaskController extends Controller 
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TTASK";
    private $awsCognito;
    
    //Initailize constructor of task controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Task(Route::current()->parameter('tenantId'),
                            Route::current()->parameter('resource'),
                            Route::current()->parameter('resourceId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save information of task to database
     * @date 21-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @return newly created task json
     */
    public function create($tenantId, $resource, $resourceId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $aclObj = new ACL($tenantId, $user, $this->schema);
            if(!$aclObj->canAddNew($this->data)){
                return ErrorHelper::getPermissionError();
            }
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $dt = $this->me->getResource();
                $dataObj = $dt["resource"];
                if($dataObj){
                    $this->data["resource"] = $resource;
                    $this->data["resourceId"] = $resourceId;
                    $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                    $outputObj = $this->me->save($obj);
                    $dataObj->push('tasks', (string) $outputObj->_id);
                    $dataObj->save();
                    //Saving value-relationship data
                    $relationshipObj = new RelationshipManager($tenantId);
                    $relationshipObj->save($this->schema->getAllFields(), 
                                            $this->data, 
                                            $outputObj, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Relationship of resource with task
                    $relData[] = $relationshipObj->getRelationship(
                                            $this->schema->getPluralName(), 
                                            $outputObj->_id,
                                            Task::FIELD_RESOURCE_ID, 
                                            $resource, 
                                            $resourceId,
                                            "_id");
                    $relationshipObj->insertData($relData, $user);
                    //Render output and return created resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/'. $resource .'/'. $resourceId . '/tasks/' . $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Saving New Task', 
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
                    return ErrorHelper::getNotFoundError($resource);
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
     * Update task of project to database
     * @date 21-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @param  string $taskId
     * @return updated task json
     */
    public function update($tenantId, $resource, $resourceId, $taskId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $dt = $this->me->getResource();
                $dataObj = $dt["resource"];
                if($dataObj){
                    //Get task based on resource id
                    $criteria = new CriteriaOption();
                    $criteria->where(Task::FIELD_TENANT, $this->me->getTenantId());
                    $criteria->where(Task::FIELD_RESOURCE, $resource);
                    $criteria->where(Task::FIELD_RESOURCE_ID, $resourceId);
                    $criteria->where("_id", $taskId);
                    $this->me->pushCriteria($criteria);
                    $this->me->applyCriteria();
                    $updateObj = $this->me->readOne($this->schema->getAttributes());
                    if($updateObj){
                        $aclObj = new ACL($tenantId, $user, $this->schema);
                        if(!$aclObj->canChange($updateObj)){
                            return ErrorHelper::getPermissionError();
                        }   
                        //Get task object by id for value-relationship
                        $oldObj = $this->me->readOne($this->schema->getAttributes());
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/'. $resource .'/'. $resourceId . '/tasks/' . $oldObj->_id;
                        $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                        //Render input and update task object
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
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/'. $resource .'/'. $resourceId . '/tasks/' . $outputObj->_id;
                        $result = $this->schema->renderOutput($outputObj,$href,$user);
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Modifying Task', 
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
                        return ErrorHelper::getNotFoundError("Task");
                    }
                }else{
                    return ErrorHelper::getExceptionError($resource);
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
     * Delete task from database
     * @date 21-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @param  string $taskId
     * @return -
     */
    public function remove($tenantId, $resource, $resourceId, $taskId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dt = $this->me->getResource();
            $dataObj = $dt["resource"];
            if($dataObj){
                //Get task based on resource id
                $criteria = new CriteriaOption();
                $criteria->where(Task::FIELD_TENANT, $this->me->getTenantId());
                $criteria->where(Task::FIELD_RESOURCE, $resource);
                $criteria->where(Task::FIELD_RESOURCE_ID, $resourceId);
                $criteria->where('_id', $taskId);
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
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/'. $resource .'/'. $resourceId . '/tasks/' . $targetObj->_id;
                        $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                        $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                                );
                        //Remove relationship of resource and task in collection reationships
                        $relationshipObj->removeRelationship(
                                                $resource, 
                                                "_id", 
                                                $resourceId, 
                                                $this->schema->getPluralName(), 
                                                Task::FIELD_RESOURCE_ID, 
                                                $targetObj->_id);
                        $dataObj->pull('tasks', (string) $targetObj->_id);
                        $dataObj->save();
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Deleting Task', 
                                                $user
                                            );
                        $revisionData[] = $revisionObj->getRevision($eventId, 
                                                $this->schema->getPluralName(), 
                                                $taskId, 
                                                null, 
                                                null, 
                                                null, 
                                                'Delete');
                        $revisionObj->insertData($revisionData, $user);
                        if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                    }else{
                        return ErrorHelper::getInUsedError("Task");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Task");
                }
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
     * list all tasks of project from database
     * @date 21-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @return list of tasks json
     */
    public function readAll($tenantId, $resource, $resourceId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dt = $this->me->getResource();
            $dataObj = $dt["resource"];
            $schemaObj = $dt["schema"];
            if($dataObj){
                $criteria = new CriteriaOption();
                $criteria->where(Task::FIELD_TENANT, $tenantId);
                $criteria->where(Task::FIELD_RESOURCE, $resource);
                $criteria->where(Task::FIELD_RESOURCE_ID, $resourceId);
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
                $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $this->me->getTenantId() . "/". $resource ."/" . $resourceId . "/tasks";
                if(isset($this->data['expand']) && $this->data['expand'] == $schemaObj->resource["singular"]){
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, 
                                                            $schemaObj->resource["singular"], $schemaObj->datasource,"", $dataObj);
                }else{
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
                }
                return response()->json($resultSet);
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
    /**
     * Get an task of project from database
     * @date 21-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @param  string $taskId
     * @return an task of project json
     */
    public function readOne($tenantId, $resource, $resourceId, $taskId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dt = $this->me->getResource();
            $dataObj = $dt["resource"];
            $schemaObj = $dt["schema"];
            if($dataObj){
                //Get task based on resource id
                $criteria = new CriteriaOption();
                $criteria->where(Task::FIELD_TENANT, $tenantId);
                $criteria->where(Task::FIELD_RESOURCE, $resource);
                $criteria->where(Task::FIELD_RESOURCE_ID, $resourceId);
                $criteria->where('_id', $taskId);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $returnObj = $this->me->readOne();
                if($returnObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canRead($returnObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/'. $resource .'/'. $resourceId . '/tasks/' . $returnObj->_id;
                    $arrTask = $this->schema->renderOutput($returnObj,$href,$user);
                    //Expand resource of task
                    if(isset($this->data['expand']) && $this->data['expand'] == $schemaObj->resource["singular"]){
                        $resourceSchema = new SchemaRender($schemaObj->datasource);
                        $resourceHref = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/'. $resource .'/'. $resourceId;
                        $arrTask[$resource] = $resourceSchema->renderOutput($dataObj, $resourceHref, $user);
                    }
                    return response()->json($arrTask);
                }else{
                    return ErrorHelper::getNotFoundError('Task');
                }
            }else{
                return ErrorHelper::getNotFoundError($resource);
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
