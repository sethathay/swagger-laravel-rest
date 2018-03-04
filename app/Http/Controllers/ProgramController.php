<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ObjLibrary\Program;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class ProgramController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TPROGRAMOBJECT";
    private $awsCognito;

    //Initailize constructor of schema controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Program();
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save basic information of program object to database
     * @date 05-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return newly created program object json
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
                //Validate request with unique name of object library
                $validator = Validator::make($this->data,[
                    'name' => 'unique:obj_libraries,name'
                ]);
                if ($validator->fails()){
                    return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                }
                //Render input and save new program object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                $outputObj = $this->me->save($obj);
                //Query to get recently added item (Reason: key of program is name not id)
                $ct = new CriteriaOption();
                $programObj = new Program();
                $ct->where(Program::FIELD_RECORD_TYPE, $programObj->getRecordType());
                $ct->where(Program::FIELD_OBJECT_TYPE, $programObj->getType());
                $ct->where('name', $outputObj->getKey());
                $programObj->pushCriteria($ct);
                $programObj->applyCriteria();
                $result = $programObj->readOne();
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager();
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $result, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/programs/' . $result->_id;
                $arrOutput = $this->schema->renderOutput($result, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager();
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Program', 
                                        $user
                                    );
                $revisionObj->save($eventId,
                                    $this->schema->getAllFields(),
                                    null,
                                    $result,
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
     * Update basic information of program object to database
     * @date 05-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @return updated program object json
     */
    public function update($proId){
        try{            
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            if(isset($this->data["type"])){ unset($this->data["type"]); }
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $criteria = new CriteriaOption();
                $criteria->where(Program::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(Program::FIELD_OBJECT_TYPE, $this->me->getType());
                $criteria->whereRaw($this->me->getDefaultFilter($proId), $this->schema->getAllFields(),$user);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $updatedObj = $this->me->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL(null, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    //Validate request with unique name of object library
                    $validator = Validator::make($this->data,[
                        'name' => 'unique:obj_libraries,name,' . $updatedObj->id . ',_id'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                    }
                    //Get program object by id for value-relationship
                    $oldObj = $this->me->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/programs/' . $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update program object
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
                    $href = env('APP_URL') .env('APP_VERSION').'/programs/' . $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Program', 
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
                    return ErrorHelper::getNotFoundError("Program");
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
     * Delete program object from database
     * @date 29-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @return -
     */
    public function remove($proId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Program::FIELD_RECORD_TYPE,$this->me->getRecordType());
            $criteria->where(Program::FIELD_OBJECT_TYPE, $this->me->getType());
            $criteria->whereRaw($this->me->getDefaultFilter($proId),$this->schema->getAllFields(),$user);
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
                    $href = env('APP_URL') .env('APP_VERSION').'/programs/' . $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Program', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $proId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Program");
                }
            }else{
                return ErrorHelper::getNotFoundError("Program");
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
     * list all programs in object library from database
     * @date 10-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return list of programs json
     */
    public function readAll(){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria   =   new CriteriaOption();
            $criteria->where(Program::FIELD_RECORD_TYPE,$this->me->getRecordType());
            //Condition filter only type is schema
            $criteria->where(Program::FIELD_OBJECT_TYPE, $this->me->getType());
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
            $dsHref = env("APP_URL").env("APP_VERSION") . "/programs";
            if(isset($this->data['expand']) && $this->data['expand'] == 'versions'){
                $ds =   $this->me->readAll(array(),'versions');
                $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "versions", "TVERSION", "versions");
            }elseif(isset($this->data['expand']) && $this->data['expand'] == 'forms'){
                $ds =   $this->me->readAll(array(),'forms');
                $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "forms", "TFORM", "forms");
            }else{
                $ds =   $this->me->readAll($this->schema->getAttributes());
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
     * list one program in object library from database
     * @date 10-Apr-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @return A program json
     */
    public function readOne($proId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Program::FIELD_RECORD_TYPE,$this->me->getRecordType());
            $criteria->where(Program::FIELD_OBJECT_TYPE, $this->me->getType());
            $criteria->whereRaw($this->me->getDefaultFilter($proId),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            //Expand versions of program
            if(isset($this->data['expand']) && $this->data['expand'] == 'versions'){
                $proObj = $this->me->readOne();
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canRead($proObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/programs/' . $proObj->_id;
                $arrProgram = $this->schema->renderOutput($proObj,$href,$user);
                $valueHref = $href . "/" . "versions";
                $ct = new CriteriaOption();
                $arrProgram['versions'] = $this->schema->renderOutputDS(
                        new SchemaRender("TVERSION"), $proObj->versions,
                        $valueHref, count($proObj->versions), $ct->offset(), $ct->limit(), $user);
            }
            //Expand forms of program
            elseif(isset($this->data['expand']) && $this->data['expand'] == 'forms'){
                $proObj = $this->me->readOne();
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canRead($proObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/programs/' . $proObj->_id;
                $arrProgram = $this->schema->renderOutput($proObj,$href,$user);
                $valueHref = $href . "/" . "forms";
                $ct = new CriteriaOption();
                $arrProgram['forms'] = $this->schema->renderOutputDS(
                        new SchemaRender("TFORM"), $proObj->forms,
                        $valueHref, count($proObj->forms), $ct->offset(), $ct->limit(), $user);
            }else{
                $proObj = $this->me->readOne($this->schema->getAttributes());
                $aclObj = new ACL(null, $user, $this->schema);
                if(!$aclObj->canRead($proObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/programs/' . $proObj->_id;
                $arrProgram = $this->schema->renderOutput($proObj,$href,$user);
            }
            return response()->json($arrProgram);
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
