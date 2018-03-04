<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Udc\Udc;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class UdcTypeController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TUDCTYPE";
    private $awsCognito;
    
    //Initailize constructor of udc type controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Udc(Route::current()->parameter('tenantId'),
                            Route::current()->parameter('sysCode'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Save udc type of project to database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @return newly created json of udc type project
     */
    public function create($tenantId,$sysCode){
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
            $this->data["sysCode"] = $this->me->getSysCode();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $criteria = new CriteriaOption();
                $uTypeObj = new Udc($tenantId,$sysCode);
                $criteria->where(Udc::FIELD_RECORD_TYPE, $uTypeObj->getRecordType());
                $criteria->where(Udc::FIELD_TENANT, $uTypeObj->getTenantId());
                $criteria->where(Udc::FIELD_SYS_CODE, $uTypeObj->getSysCode());
                $criteria->where('code', $this->data['code']);
                $uTypeObj->pushCriteria($criteria);
                $uTypeObj->applyCriteria();
                $findObj = $uTypeObj->readOne();
                if(isset($findObj)){
                    //Validate request with unique code of udc type
                    $validator = Validator::make($this->data,[
                        'code' => 'unique:udcs,code'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                    }
                }
                //Render input and save new udc type object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");               
                $outputObj = $this->me->save($obj);
                //Query to get recently added item (Reason: key of udc type is code not id)
                $ct = new CriteriaOption();
                $udcTypeOb = new Udc($tenantId, $sysCode);
                $ct->where(Udc::FIELD_RECORD_TYPE, $udcTypeOb->getRecordType());
                $ct->where(Udc::FIELD_TENANT, $udcTypeOb->getTenantId());
                $ct->where(Udc::FIELD_SYS_CODE, $udcTypeOb->getSysCode());
                $ct->where('code', $outputObj->getKey());
                $udcTypeOb->pushCriteria($ct);
                $udcTypeOb->applyCriteria();
                $result = $udcTypeOb->readOne();
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager($tenantId);
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $result, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/'. $result->_id;
                $arrOutput = $this->schema->renderOutput($result, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager($tenantId);
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Udc Type', 
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
     * Update basic information of udc type project to database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @param  string $typeId
     * @return updated udc type project object json
     */
    public function update($tenantId,$sysCode,$typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            if(isset($this->data["recordType"])){ unset($this->data["recordType"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $this->data["sysCode"] = $this->me->getSysCode();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Getting udc type by sys code and id
                $criteria = new CriteriaOption();
                $uTypeObj = new Udc($tenantId,$sysCode);
                $criteria->where(Udc::FIELD_RECORD_TYPE, $uTypeObj->getRecordType());
                $criteria->where(Udc::FIELD_TENANT, $uTypeObj->getTenantId());
                $criteria->where(Udc::FIELD_SYS_CODE, $uTypeObj->getSysCode());
                $criteria->whereRaw($uTypeObj->getDefaultFilter($typeId),$this->schema->getAllFields(),$user);
                $uTypeObj->pushCriteria($criteria);
                $uTypeObj->applyCriteria();
                $updatedObj = $uTypeObj->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    if(isset($updatedObj) && $updatedObj->code != $this->data['code']){
                        //Validate request with unique code of udc type
                        $validator = Validator::make($this->data,[
                            'code' => 'unique:udcs,code'
                        ]);
                        if ($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('code'));
                        }
                    }
                    //Get udc type object by id for value-relationship
                    $oldObj = $uTypeObj->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/'. $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update udc type object
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
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/'. $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj, $href, $user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Udc Type', 
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
                    return ErrorHelper::getNotFoundError("Udc Type");
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
     * list all information of udc type project from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @return list of udc type project json
     */
    public function readAll($tenantId, $sysCode){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Udc::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Udc::FIELD_TENANT, $tenantId);
            $criteria->where(Udc::FIELD_SYS_CODE, $sysCode);
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
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/systems/" . $sysCode . "/types";
            if(isset($this->data['expand']) && $this->data['expand'] == 'values'){
                $ds = $this->me->readAll();
                $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $dsHref, $recordSize, $offset, $limit, $user, "values", "TUDCVALUE", "values");
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
     * list one udc type in collection udcs from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @param  string $typeId
     * @return A udc type project json
     */
    public function readOne($tenantId,$sysCode,$typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Udc::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Udc::FIELD_TENANT, $this->me->getTenantId());
            $criteria->where(Udc::FIELD_SYS_CODE, $this->me->getSysCode());
            $criteria->whereRaw($this->me->getDefaultFilter($typeId),$this->schema->getAllFields(),$user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            //Read all but return only first found result
            if(isset($this->data['expand']) && $this->data['expand'] == 'values'){
                $updatedObj = $this->me->readOne(array(),'values');
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($updatedObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/'. $updatedObj->_id;
                $arrType = $this->schema->renderOutput($updatedObj,$href,$user);
                $valueHref = $href . "/" . "values";
                $ct = new CriteriaOption();
                $arrType['values'] = $this->schema->renderOutputDS(
                        new SchemaRender("TUDCVALUE"), $updatedObj->values,
                        $valueHref, count($updatedObj->values), $ct->offset(), $ct->limit(), $user);
            }else{
                $updatedObj = $this->me->readOne($this->schema->getAttributes());
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($updatedObj)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/'. $updatedObj->_id;
                $arrType = $this->schema->renderOutput($updatedObj,$href,$user);
            }
            return response()->json($arrType);
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
     * Delete udc type project object from database
     * @date 17-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $sysCode
     * @param  string $typeId
     * @return -
     */
    public function remove($tenantId,$sysCode,$typeId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(Udc::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Udc::FIELD_TENANT, $tenantId);
            $criteria->where(Udc::FIELD_SYS_CODE, $sysCode);
            $criteria->whereRaw($this->me->getDefaultFilter($typeId),$this->schema->getAllFields(),$user);
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
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantId . '/systems/'. $sysCode . '/types/'. $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting Udc Type', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $typeId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("Udc Type");
                }
            }else{
                return ErrorHelper::getNotFoundError("Udc Type");
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