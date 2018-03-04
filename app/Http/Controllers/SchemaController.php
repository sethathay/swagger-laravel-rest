<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class SchemaController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TSCHEMAOBJECT";
    private $awsCognito;
    
    //Initailize constructor of schema controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Schema();
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save basic information of schema object to database
     * @date 05-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return newly created schema object json
     */
    public function create(){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $aclObj = new ACL(null, $user, $this->schema);
            if(!$aclObj->canAddNew($this->data)){
                return ErrorHelper::getPermissionError();
            }
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["type"] = $this->me->getType();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Validate request with unique name of menu
                $validator = Validator::make($this->data,[
                    'name' => 'unique:obj_libraries,name'
                ]);
                if ($validator->fails()){
                    return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                }
                //Render input and save new schema object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                $outputObj = $this->me->save($obj);
                //Query to get recently added item (Reason: key of schema is name not id)
                $ct = new CriteriaOption();
                $schemaObj = new Schema();
                $ct->where(Schema::FIELD_RECORD_TYPE, $schemaObj->getRecordType());
                $ct->where(Schema::FIELD_OBJECT_TYPE, $schemaObj->getType());
                $ct->where('name', $outputObj->getKey());
                $schemaObj->pushCriteria($ct);
                $schemaObj->applyCriteria();
                $result = $schemaObj->readOne();
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager();
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $result, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/schemas/' . $result->_id;
                $arrOutput = $this->schema->renderOutput($result, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager();
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Schema', 
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
     * Update basic information of schema object to database
     * @date 05-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $schemaId
     * @return updated schema object json
     */
    public function update($schemaId){        
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            if(isset($this->data["type"])){ unset($this->data["type"]); }
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $criteria = new CriteriaOption();
                $criteria->where(Schema::FIELD_RECORD_TYPE,$this->me->getRecordType());
                $criteria->where(Schema::FIELD_OBJECT_TYPE, $this->me->getType());
                $criteria->whereRaw($this->me->getDefaultFilter($schemaId),$this->schema->getAllFields(),$user);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $updatedObj =   $this->me->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL(null, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    //Validate request with unique name of menu
                    $validator = Validator::make($this->data,[
                        'name'      =>  'unique:obj_libraries,name,' . $updatedObj->id . ',_id'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                    }
                    //Get schema object by id for value-relationship
                    $oldObj = $this->me->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/schemas/' . $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update schema object
                    $obj = $this->schema->renderInput($this->data, $updatedObj, $user, "U");
                    $outputObj = $this->me->save($obj);
                    //Updating value-relationship data
                    $relationshipObj = new RelationshipManager();
                    $relationshipObj->update($this->schema->getAllFields(), 
                                            $this->data, 
                                            $oldTargetObj, 
                                            $outputObj, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Render output and return updated resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/schemas/' . $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Schema', 
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
                    return ErrorHelper::getNotFoundError("Schema");
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
     * Delete schema object from database
     * @date 29-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $schemaId
     * @return -
     */
    public function remove($schemaId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Schema::FIELD_RECORD_TYPE,$this->me->getRecordType());
            $criteria->where(Schema::FIELD_OBJECT_TYPE, $this->me->getType());
            $criteria->whereRaw($this->me->getDefaultFilter($schemaId),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $targetObj = $this->me->readOne($this->schema->getAttributes());
            if($targetObj){
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canDelete($targetObj)){
                    return ErrorHelper::getPermissionError();
                }
                $relationshipObj = new RelationshipManager();
                if(!$relationshipObj->inUsed($targetObj, $this->schema->getPluralName())){
                    $href = env('APP_URL') .env('APP_VERSION').'/schemas/' . $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Schema', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $schemaId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Schema");
                }
            }else{
                return ErrorHelper::getNotFoundError("Schema");
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
     * list all schemas in object library from database
     * @date 10-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return list of schema json
     */
    public function readAll(){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria   =   new CriteriaOption();
            $criteria->where(Schema::FIELD_RECORD_TYPE, $this->me->getRecordType());
            //Condition filter only type is schema
            $criteria->where(Schema::FIELD_OBJECT_TYPE, $this->me->getType());
            $aclObj = new ACL(null, $user, $this->schema);
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
            $ds =   $this->me->readAll($this->schema->getAttributes());
            $href = env("APP_URL").env("APP_VERSION") . "/schemas";
            $resultSet['href'] = '';
            $resultSet['size'] = 0;
            $resultSet['limit'] = 25;
            $resultSet['offset'] = 0;
            $resultSet['items'] = $ds;
            // $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $href, $recordSize, $offset, $limit, $user);
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
     * list one schema in object library from database
     * @date 10-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $schemaId
     * @return A schema json
     */
    public function readOne($schemaId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Schema::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Schema::FIELD_OBJECT_TYPE, $this->me->getType());
            $criteria->whereRaw($this->me->getDefaultFilter($schemaId),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $schemaObj = $this->me->readOne($this->schema->getAttributes());
            if($schemaObj){
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canRead($schemaObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/schemas/' . $schemaObj->_id;
                return response()->json($this->schema->renderOutput($schemaObj,$href,$user));
            }else{
                return ErrorHelper::getNotFoundError('Schema');
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
