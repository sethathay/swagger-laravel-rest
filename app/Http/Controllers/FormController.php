<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\ObjLibrary\Form;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class FormController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TFORM";
    private $awsCognito;
    
    //Initailize constructor of schema controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Form(Route::current()->parameter('proId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save form of program object to database
     * @date 11-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @return newly created form object json
     */
    public function create($proId){
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
            $this->data["programName"] = $proId;
//            $validateSMS = $this->schema->validate($this->data);
//            if($validateSMS["status"]){
                $proObj = $this->me->getProgram($proId);
                if($proObj){
                    $validator = Validator::make($this->data,[
                        'name' => 'unique:obj_libraries,name'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                    }
                    //Render input and save new form object
                    $saveObj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                    $outputObj = $this->me->save($saveObj);
                    //Query to get recently added item (Reason: key of form is name not id)
                    $ct = new CriteriaOption();
                    $frmObj = new Form($proId);
                    $ct->where(Form::FIELD_RECORD_TYPE, $frmObj->getRecordType());
                    $ct->where(Form::FIELD_PROGRAM, $proObj->name);
                    $ct->where('name', $outputObj->getKey());
                    $frmObj->pushCriteria($ct);
                    $frmObj->applyCriteria();
                    $result = $frmObj->readOne();
                    //Saving value-relationship data
                    $relationshipObj = new RelationshipManager();
                    $relationshipObj->save($this->schema->getAllFields(), 
                                            $this->data, 
                                            $result, 
                                            $this->schema->getPluralName(), 
                                            $user);
                    //Render output and return created resource in json
                    $href = env('APP_URL') .env('APP_VERSION').'/programs/'. $proId . '/forms/' . $result->_id;
                    $arrOutput = $this->schema->renderOutput($result, $href, $user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager();
                    $eventId = $revisionObj->newEvent(
                                            'Event - Saving New Form', 
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
                    return ErrorHelper::getNotFoundError("Program");
                }
//            }else{
//                return ErrorHelper::getExceptionError($validateSMS["message"]);
//            }
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
     * Update form of program object to database
     * @date 11-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @param  string $formId
     * @return updated form object json
     */
    public function update($proId, $formId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["programName"] = $proId;
//            $validateSMS = $this->schema->validate($this->data);
//            if($validateSMS["status"]){
                $proObj = $this->me->getProgram($proId);
                if($proObj){
                    $criteria = new CriteriaOption();
                    $criteria->where(Form::FIELD_RECORD_TYPE, $this->me->getRecordType());
                    $criteria->where(Form::FIELD_PROGRAM, $proObj->name);
                    $criteria->whereRaw($this->me->getDefaultFilter($formId),$this->schema->getAllFields(),$user);
                    $this->me->pushCriteria($criteria);
                    $this->me->applyCriteria();
                    $updateObj  =   $this->me->readOne($this->schema->getAttributes());
                    if($updateObj){
                        $aclObj = new ACL(null, $user, $this->schema);
                        if(!$aclObj->canChange($updateObj)){
                            return ErrorHelper::getPermissionError();
                        }
                        //Validate request with unique name of object library
                        $validator = Validator::make($this->data,[
                            'name' => 'unique:obj_libraries,name,' . $updateObj->id . ',_id'
                        ]);
                        if ($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('name'));
                        }
                        //Get form object by id for value-relationship
                        $oldObj = $this->me->readOne($this->schema->getAttributes());
                        $href = env('APP_URL') .env('APP_VERSION').'/programs/'. $proId . '/forms/' . $oldObj->_id;
                        $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                        //Render input and update form object
                        $saveObj    =   $this->schema->renderInput($this->data,$updateObj,$user,"U");
                        $outputObj  =   $this->me->save($saveObj);
                        //Updating value-relationship data
                        $relationshipObj = new RelationshipManager();
                        $relationshipObj->update($this->schema->getAllFields(), 
                                                $this->data, 
                                                $oldTargetObj, 
                                                $outputObj, 
                                                $this->schema->getPluralName(), 
                                                $user);
                        //Render output and return updated resource in json
                        $href = env('APP_URL') .env('APP_VERSION').'/programs/'. $proId . '/forms/' . $outputObj->_id;
                        $result = $this->schema->renderOutput($outputObj, $href, $user);
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager();
                        $eventId = $revisionObj->newEvent(
                                                'Event - Modifying Form', 
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
                        return ErrorHelper::getNotFoundError("Form");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Program");
                }
//            }else{
//                return ErrorHelper::getExceptionError($validateSMS["message"]);
//            }
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
     * Get a form of program object from database
     * @date 11-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @param  string $formId
     * @return a form object json
     */
    public function readOne($proId, $formId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $proObj = $this->me->getProgram($proId);
            if($proObj){
                $criteria = new CriteriaOption();
                $criteria->where(Form::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(Form::FIELD_PROGRAM, $proObj->name);
                $criteria->whereRaw($this->me->getDefaultFilter($formId),$this->schema->getAllFields(),$user);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $returnObjDS = $this->me->readOne();
                if($returnObjDS){
                    $aclObj = new ACL(null, $user, $this->schema);
                    if(!$aclObj->canRead($returnObjDS)){
                        return ErrorHelper::getPermissionError();
                    }
                    $href = env('APP_URL') .env('APP_VERSION').'/programs/'. $proId . '/forms/' . $returnObjDS->_id;
                    $arrForm = $this->schema->renderOutput($returnObjDS, $href, $user);
                    if(isset($this->data['expand']) && $this->data['expand'] == 'program'){
                        $proSchema = new SchemaRender("TPROGRAMOBJECT");
                        $proHref = env('APP_URL') .env('APP_VERSION').'/programs/'. $proId;
                        $arrForm["program"] = $proSchema->renderOutput($proObj, $proHref, $user);
                    }
                    return response()->json($arrForm);
                }else{
                    return ErrorHelper::getNotFoundError('Form');
                }
            }else{
                return ErrorHelper::getNotFoundError('Program');
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
     * list all forms of object library from database
     * @date 11-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @return list of form json
     */
    public function readAll($proId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $programObj = $this->me->getProgram($proId);
            if($programObj){
                $criteria   =   new CriteriaOption();
                $criteria->where(Form::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(Form::FIELD_PROGRAM, $programObj->name);
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
                $ds = $this->me->readAll();
                $dsHref = env("APP_URL").env("APP_VERSION") . "/programs/" . $proId . "/forms";
                if(isset($this->data['expand']) && $this->data['expand'] == 'program'){
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "program", "TPROGRAMOBJECT","", $programObj);
                }else{
                    $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user);
                }
                return response()->json($resultSet);
            }else{
                return ErrorHelper::getNotFoundError('Program');
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
     * Delete form of program object to database
     * @date 11-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $proId
     * @param  string $formId
     * @return -
     */
    public function remove($proId, $formId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $proObj = $this->me->getProgram($proId);
            if($proObj){
                $criteria = new CriteriaOption();
                $criteria->where(Form::FIELD_RECORD_TYPE, $this->me->getRecordType());
                $criteria->where(Form::FIELD_PROGRAM, $proObj->name);
                $criteria->whereRaw($this->me->getDefaultFilter($formId),$this->schema->getAllFields(),$user);
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
                        $href = env('APP_URL') .env('APP_VERSION').'/programs/'. $proId . '/forms/' . $targetObj->_id;
                        $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                        $relationshipObj->remove($this->schema->getAllFields(), 
                                                    $tObj, 
                                                    $this->schema->getPluralName()
                                                );
                        //Saving audit trail and data versioning
                        $revisionObj = new RevisionManager();
                        $eventId = $revisionObj->newEvent(
                                                'Event - Deleting Form', 
                                                $user
                                            );
                        $revisionData[] = $revisionObj->getRevision($eventId, 
                                                $this->schema->getPluralName(), 
                                                $formId, 
                                                null, 
                                                null, 
                                                null, 
                                                'Delete');
                        $revisionObj->insertData($revisionData, $user);
                        if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                    }else{
                        return ErrorHelper::getInUsedError("Form");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Form");
                }
            }else{
                return ErrorHelper::getNotFoundError('Program');
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