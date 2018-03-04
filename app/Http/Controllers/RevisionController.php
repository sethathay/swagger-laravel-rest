<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Revision\Revision;
use App\Http\Controllers\Revision\Event;
use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;

class RevisionController extends Controller {
    
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TREVISION";
    private $schemaEvent = "TEVENT";
    private $awsCognito;
    
    //Initailize constructor of menu controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Revision(Route::current()->parameter('tenantId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Rendering old and new field value based on field schema
     * @date 30-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  array $ds
     * @param  string $resource
     * @param  string $user
     * @return array $ds
     */
    private function renderFields($ds, $resource, $user){
        //GET SCHEMA BASED ON PLURAL RESOURCE NAME
        $schemaResource = new Schema();
        $ct = new CriteriaOption();
        $ct->where('resource.plural', $resource);
        $schemaResource->pushCriteria($ct);
        $schemaResource->applyCriteria();
        $resourceFields = $schemaResource->readOne()->fields;
        //RENDER OLD & NEW VALUE BASED ON FIELD
        foreach($ds as $datasource){
            //Search to get index of column field_id in fields list
            $ind = array_search($datasource['field'], array_column($resourceFields, 'field_id'));
            //Prepare old and new value data into array for rendering
            $dataOld = array($datasource['field'] => $datasource['old_value']);
            $dataNew = array($datasource['field'] => $datasource['new_value']);
            $renderSchema = new SchemaRender();
            $renderSchema->setAllFields(array($resourceFields[$ind]));
            //Getting old and new value after rendering process
            $valueOld = $renderSchema->renderOutput((object)$dataOld, "", $user);
            $valueNew = $renderSchema->renderOutput((object)$dataNew, "", $user);
            //Modifying Field, Old Value, New Value
            $datasource['field'] = $resourceFields[$ind]['external_id'];
            $datasource['old_value'] = $valueOld[$resourceFields[$ind]['external_id']];
            $datasource['new_value'] = $valueNew[$resourceFields[$ind]['external_id']];
        }
        //END REDERING PROCESS
        return $ds;
    }
    /**
     * Rendering old and new field value based on field schema
     * @date 30-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  array $ds
     * @param  string $resource
     * @param  string $user
     * @return array $ds
     */
    private function getRevertFields($ds, $resource, $user, $revertedColumns){
        $valueOld = array();
        //GET SCHEMA BASED ON PLURAL RESOURCE NAME
        $schemaResource = new Schema();
        $ct = new CriteriaOption();
        $ct->where('resource.plural', $resource);
        $schemaResource->pushCriteria($ct);
        $schemaResource->applyCriteria();
        $resourceFields = $schemaResource->readOne()->fields;
        //GETTING REVERT VALUE BASED ON FIELD
        foreach($ds as $datasource){
            if($datasource['resource'] == $resource){
                //Search to get index of column field_id in fields list
                $ind = array_search($datasource['field'], array_column($resourceFields, 'field_id'));
                if(count($revertedColumns) != 0){
                    //Excluded field that is not in $revertedColumns
                    if(!in_array($resourceFields[$ind]['external_id'], $revertedColumns)) break;
                }
                //Prepare old value data into array for rendering
                $dataOld = array($datasource['field'] => $datasource['old_value']);
                $renderSchema = new SchemaRender();
                $renderSchema->setAllFields(array($resourceFields[$ind]));
                //Getting old value after rendering process
                $revertValue = $renderSchema->renderOutput((object)$dataOld, "", $user);
                switch($resourceFields[$ind]['edit_type']){
                    case "weUdc" :
                        $valueOld[$resourceFields[$ind]['external_id']] = $revertValue[$resourceFields[$ind]['external_id']]['code'];
                        break;
                    case "wePhone" :
                    case "weEmail" :
                    case "weAddress" :
                    case "weSocial" :
                    case "weCurrency" :
                        $i = 0;
                        foreach($revertValue[$resourceFields[$ind]['external_id']] as $weControl){
                            $revertValue[$resourceFields[$ind]['external_id']][$i]['type'] = $weControl['type']['code'];
                            $i++;
                        }
                        $valueOld[$resourceFields[$ind]['external_id']] = $revertValue[$resourceFields[$ind]['external_id']]; 
                    default:
                        $valueOld[$resourceFields[$ind]['external_id']] = $revertValue[$resourceFields[$ind]['external_id']]; 
                        break;
                }
            }
        }
        //END REDERING PROCESS
        return $valueOld;
    }
    /**
     * Returns the data about the specific revision on an item
     * @date 29-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @param  int $revision
     * @return list of revision json
     */
    public function readOne($tenantId, $resource, $resourceId, $revision){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Revision::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Revision::FIELD_TENANT, $tenantId);
            $criteria->where(Revision::FIELD_RESOURCE, $resource);
            $criteria->where(Revision::FIELD_RESOURCE_ID, $resourceId);
            $criteria->where('revision_no', (int)$revision);
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
            $dsource = $this->me->readAll();
            $ds = $this->renderFields($dsource, $resource, $user);
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $this->me->getTenantId() . "/". $resource ."/" . $resourceId . "/revision/" . $revision;
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
     * Returns all the revisions that have been made to an item
     * @date 30-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @return list of revision json
     */
    public function readAll($tenantId, $resource, $resourceId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Revision::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Revision::FIELD_TENANT, $tenantId);
            $criteria->where(Revision::FIELD_RESOURCE, $resource);
            $criteria->where(Revision::FIELD_RESOURCE_ID, $resourceId);
            $criteria->orderBy('revision_no', 'DESC');
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
            $dsource = $this->me->readAll();
            $ds = $this->renderFields($dsource, $resource, $user);
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $this->me->getTenantId() . "/". $resource ."/" . $resourceId . "/revision";
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
     * Returns the difference in fields values between the two revisions
     * @date 30-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @param  int $revisionFrom
     * @param  int $revisionTo
     * @return list of different revision json
     */
    public function readDiff($tenantId, $resource, $resourceId, $revisionFrom, $revisionTo){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Revision::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Revision::FIELD_TENANT, $tenantId);
            $criteria->where(Revision::FIELD_RESOURCE, $resource);
            $criteria->where(Revision::FIELD_RESOURCE_ID, $resourceId);
            $criteria->whereIn('revision_no', [(int)$revisionFrom, (int)$revisionTo]);
            $criteria->orderBy('revision_no', 'DESC');
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
            $dsource = $this->me->readAll();
            $ds = $this->renderFields($dsource, $resource, $user);
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $this->me->getTenantId() . "/". $resource ."/" . $resourceId . "/revision/" . $revisionFrom . "/" . $revisionTo;
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
     * Reverts the item to the values in the given revision. This will undo any changes made after the given revision.
     * @date 30-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @param  int $revision
     * @return list of revision json
     */
    public function revert($tenantId, $resource, $resourceId, $revision){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Revision::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Revision::FIELD_TENANT, $tenantId);
            $criteria->where(Revision::FIELD_RESOURCE, $resource);
            $criteria->where(Revision::FIELD_RESOURCE_ID, $resourceId);
            $criteria->where('revision_no', (int)$revision);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $dsource = $this->me->readAll();
            if(count($dsource) != 0){
                //For processing of specific columns provided by user
                $columns = [];
                if(isset($this->data['fields'])){
                    $columns = $this->data['fields'];
                }
                $revertData = $this->getRevertFields($dsource, $resource, $user, $columns);
                //GET SCHEMA BASED ON PLURAL RESOURCE NAME
                $schemaResource = new Schema();
                $ct = new CriteriaOption();
                $ct->where('resource.plural', $resource);
                $schemaResource->pushCriteria($ct);
                $schemaResource->applyCriteria();
                $schemaObj = $schemaResource->readOne();
                if($schemaObj){
                    //GET MODEL OF FOUNDED SCHEMA TO GET UPDATED OBJECT
                    $model = $schemaObj->crud['model'];
                    $resourceObj = new $model;
                    $crt = new CriteriaOption();
                    $crt->where('_id', $resourceId);
                    $resourceObj->pushCriteria($crt);
                    $resourceObj->applyCriteria();
                    $updatedObj = $resourceObj->readOne();
                    if($updatedObj){
                        $revertSchema = new SchemaRender($schemaObj->name);
                        //Get resource object by id for value-relationship
                        $oldObj = $resourceObj->readOne();
                        $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/' . $resource . '/'. $resourceId;
                        $oldTargetObj = $revertSchema->renderOutput($oldObj, $href, $user);
                        //Render input and update resource object
                        $saveObj = $revertSchema->renderInput($revertData, $updatedObj, $user, "U");
                        $outputObj = $resourceObj->save($saveObj);
                        //Updating value-relationship data
                        $relationshipObj = new RelationshipManager($tenantId);
                        $relationshipObj->update($revertSchema->getAllFields(), 
                                                $revertData, 
                                                $oldTargetObj, 
                                                $outputObj, 
                                                $revertSchema->getPluralName(), 
                                                $user);
                        //Render output and return updated resource in json
                        $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/'. $resource .'/'. $outputObj->_id;
                        $result = $revertSchema->renderOutput($outputObj,$href,$user);
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager($tenantId);
                        $eventId = $revisionObj->newEvent(
                                                'Event - Reverting '. $resource . ' that has id ' . $resourceId, 
                                                $user
                                            );
                        $revisionObj->save($eventId,
                                            $revertSchema->getAllFields(),
                                            $oldTargetObj,
                                            $result,
                                            'Rollback',
                                            $revertSchema->getPluralName(),
                                            $user);
                        //Return result of updating resource in JSON format
                        return response()->json($result);
                    }else{
                        return ErrorHelper::getNotFoundError('Resource');
                    }
                }else{
                    return ErrorHelper::getNotFoundError('Schema Object of Resource');
                }
            }else{
                return ErrorHelper::getNotFoundError('Revision');
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
     * Reverts the item to the values in the given event. This will undo any changes made after the given event.
     * @date 04-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $event
     * @return list of event json
     */
    public function revertEvent($tenantId, $event){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Revision::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Revision::FIELD_TENANT, $tenantId);
            $criteria->where(Revision::FIELD_EVENT, $event);
            $criteria->orderBy('resource', 'ASC');
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $dsource = $this->me->readAll();
            if(count($dsource) != 0){
                $resource = "";
                $results = [];
                $revisionObj = new RevisionManager($tenantId);
                $eventId = $revisionObj->newEvent(
                                'Event - Reverting event that has id ' . $event, 
                                $user
                            );
                foreach($dsource as $item){
                    if($resource != $item->resource){
                        $resource = $item->resource;
                        $resourceId = $item->resource_id;
                        $columns = [];
                        //For filtering of resource lists
                        if(isset($this->data['resources'])){
                            if(!in_array($resource, $this->data['resources'])) continue;
                            //For processing of specific columns provided by user
                            if(isset($this->data['fields'])){
                                $columns = $this->data['fields'];
                            }
                        }
                        $revertData = $this->getRevertFields($dsource, $resource, $user, $columns);
                        //GET SCHEMA BASED ON PLURAL RESOURCE NAME
                        $schemaResource = new Schema();
                        $ct = new CriteriaOption();
                        $ct->where('resource.plural', $resource);
                        $schemaResource->pushCriteria($ct);
                        $schemaResource->applyCriteria();
                        $schemaObj = $schemaResource->readOne();
                        if($schemaObj){
                            //GET MODEL OF FOUNDED SCHEMA TO GET UPDATED OBJECT
                            $model = $schemaObj->crud['model'];
                            $resourceObj = new $model;
                            $crt = new CriteriaOption();
                            $crt->where('_id', $resourceId);
                            $resourceObj->pushCriteria($crt);
                            $resourceObj->applyCriteria();
                            $updatedObj = $resourceObj->readOne();
                            if($updatedObj){
                                $revertSchema = new SchemaRender($schemaObj->name);
                                //Get resource object by id for value-relationship
                                $oldObj = $resourceObj->readOne();
                                $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/' . $resource . '/'. $resourceId;
                                $oldTargetObj = $revertSchema->renderOutput($oldObj, $href, $user);
                                //Render input and update resource object
                                $saveObj = $revertSchema->renderInput($revertData, $updatedObj, $user, "U");
                                $outputObj = $resourceObj->save($saveObj);
                                //Updating value-relationship data
                                $relationshipObj = new RelationshipManager($tenantId);
                                $relationshipObj->update($revertSchema->getAllFields(), 
                                                        $revertData, 
                                                        $oldTargetObj, 
                                                        $outputObj, 
                                                        $revertSchema->getPluralName(), 
                                                        $user);
                                //Render output and return updated resource in json
                                $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/'. $resource .'/'. $outputObj->_id;
                                $result = $revertSchema->renderOutput($outputObj,$href,$user);
                                //Saving audit trail and data versioning
                                $revObj = new RevisionManager($tenantId);
                                $revObj->save($eventId,
                                                    $revertSchema->getAllFields(),
                                                    $oldTargetObj,
                                                    $result,
                                                    'Rollback',
                                                    $revertSchema->getPluralName(),
                                                    $user);
                                $results[] = $result;
                            }else{
                                return ErrorHelper::getNotFoundError('Resource');
                            }
                        }else{
                            return ErrorHelper::getNotFoundError('Schema Object of Resource');
                        }
                    }
                }
                //Return result of updating resource in JSON format
                return response()->json($results);
            }else{
                return ErrorHelper::getNotFoundError('Revision');
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
     * Returns the data about the specific event
     * @date 04-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $event
     * @return list of an event json
     */
    public function readEvent($tenantId, $event){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $eventObj = new Event($tenantId);
            $schemaObj = new SchemaRender($this->schemaEvent);
            $criteria = new CriteriaOption();
            $criteria->where(Event::FIELD_TENANT, $eventObj->getTenantId());
            $criteria->where(Event::FIELD_EVENT, $event);
            if(isset($this->data['expand']) && $this->data['expand'] == 'revisions'){
                $eventObj->pushCriteria($criteria);
                $eventObj->applyCriteria();
                $outputObj = $eventObj->readAll();
                if($outputObj){
                    $ind = array_search($eventObj->getRecordType(), array_column($outputObj->toArray(), 'record_type'));
                    $eventResult = $outputObj[$ind];
                    unset($outputObj[$ind]);
                    //Render output and return event resource in json
                    $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/events/'. $event;
                    $result = $schemaObj->renderOutput($eventResult, $href, $user);
                    $revisionHref = $href . "/" . "revisions";
                    $ct = new CriteriaOption();
                    $result['revisions'] = $this->schema->renderOutputDS(
                            $this->schema, $outputObj, $revisionHref, 
                            count($outputObj), $ct->offset(), $ct->limit(), $user);
                    return response()->json($result);
                }else{
                    return ErrorHelper::getNotFoundError('Event');
                }
            }else{
                $criteria->where(Event::FIELD_RECORD_TYPE, $eventObj->getRecordType());
                $eventObj->pushCriteria($criteria);
                $eventObj->applyCriteria();
                $outputObj = $eventObj->readOne();
                if($outputObj){
                    //Render output and return event resource in json
                    $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/events/'. $event;
                    return response()->json($schemaObj->renderOutput($outputObj, $href, $user));
                }else{
                    return ErrorHelper::getNotFoundError('Event');
                }
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
     * Returns all the events that have been made
     * @date 04-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @return list of events json
     */
    public function readEvents($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $eventObj = new Event($tenantId);
            $schemaObj = new SchemaRender($this->schemaEvent);
            $criteria = new CriteriaOption();
            $criteria->where(Event::FIELD_RECORD_TYPE, $eventObj->getRecordType());
            $criteria->where(Event::FIELD_TENANT, $eventObj->getTenantId());
            $eventObj->pushCriteria($criteria);
            $eventObj->applyCriteria();
            $recordSize = $eventObj->count();
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
            $eventObj->pushCriteria($criteria);
            $eventObj->applyCriteria();
            $ds = $eventObj->readAll($schemaObj->getAttributes());
            $href = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/events";
            $resultSet = $schemaObj->renderOutputDS($schemaObj, $ds, $href, $recordSize, $offset, $limit, $user);
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
}
