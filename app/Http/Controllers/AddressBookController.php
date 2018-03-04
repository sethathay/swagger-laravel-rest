<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\AddressBook\AddressBook;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;
/**
* Class AddressBookController contains all functions used to expose address book data in restful API.
* 
* @since version 1.0
* @deprecated since version 0.0
*/
class AddressBookController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TADDRESSBOOK";
    private $awsCognito;

    //Initailize constructor of object library controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new AddressBook(Route::current()->parameter('tenantId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    
    /**
     * Get all addressbook resources
     * @date 15-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  string $tenantId
     * @return list of all resources in json format.
     */
    /**
    * @SWG\Post(
    *   path="/tenants/{tenantId}/addressbooks/get",
    *	 tags={"AddressBook"},
    *   summary="get all addressbooks",
    *   operationId="getAllAddressBooks",
    *   produces={"application/json"},
    *	 @SWG\Parameter(  
    *     name="tenantId",  
    *     in="path",  
    *     description="id of tenant to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="filter",  
    *     in="body",  
    *     description="filter addressbooks by condition provided",  
    *     required=false,
    *	   @SWG\Schema(type="object")
    *   ),
    *	 @SWG\Parameter(  
    *     name="offset",  
    *     in="query",  
    *     description="The number of items to skip before starting to collect the result set",  
    *     required=false,  
    *     type="integer",
    *     format="int32",
    *	  default=0
    *   ),
    *	 @SWG\Parameter(  
    *     name="limit",  
    *     in="query",  
    *     description="The number of items to return",  
    *     required=false,  
    *     type="integer",
    *     format="int32",
    *     default=25
    *   ),
    *   @SWG\Response(response=200, description="return all addressbooks in json format"),
    *	 security={
    *         {
    *             "default": {}
    *         }
    *     }
    * )
    *
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function readAll($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(AddressBook::FIELD_TENANT,$tenantId);
            $aclObj = new ACL($tenantId, $user, $this->schema);
            $aclFilters = $aclObj->getFiltersOfACL();
            if(count($aclFilters) > 0 ){
                $criteria->whereRaw($aclFilters, $this->schema->getAllFields(), $user);
            }
            if(isset($this->data['filter'])){
                $criteria->whereRaw($this->data['filter'],$this->schema->getAllFields(),$user);
            }
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $recordSize = $this->me->count();
            if(isset($this->data['offset'])){
                $offset     =   $criteria->offset($this->data['offset']);
            }else{
                $offset     =   $criteria->offset();
            }
            if(isset($this->data['limit'])){
                $limit      =   $criteria->limit($this->data['limit']);
            }else{
                $limit      =   $criteria->limit();
            }
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $ds = $this->me->readAll($this->schema->getAttributes());
            $href = env("APP_URL").env("APP_VERSION") . "/tenants/" . $tenantId . "/addressbooks";
            $resultSet = $this->schema->renderOutputDS($this->schema, $ds, $href, $recordSize, $offset, $limit, $user);
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
     * Get an addressbook resource by id
     * @date 15-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  string $tenantId
     * @param  string $addressBookId
     * @return an addressbook resource in json format.
     */
    /**
    * @SWG\Get(
    *   path="/tenants/{tenantId}/addressbooks/{addressBookId}",
    *	 tags={"AddressBook"},
    *   summary="get addressbook by id",
    *   operationId="getAddressBookById",
    *   produces={"application/json"},
    *	 @SWG\Parameter(  
    *     name="tenantId",  
    *     in="path",  
    *     description="id of tenant to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="addressBookId",  
    *     in="path",  
    *     description="id of addressbook to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *   @SWG\Response(response=200, description="return an addressbook resource in json format"),
    *	 security={
    *         {
    *             "default": {}
    *         }
    *     }
    * )
    *
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function readOne($tenantId, $addressBookId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(AddressBook::FIELD_TENANT, $tenantId);
            $criteria->where('_id', $addressBookId);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $outputObj = $this->me->readOne($this->schema->getAttributes());
            if($outputObj){
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($outputObj)){
                    return ErrorHelper::getPermissionError();
                }
                //Render output and return addressbook resource in json
                $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/addressbooks/'. $outputObj->_id;
                return response()->json($this->schema->renderOutput($outputObj,$href,$user));
            }else{
                return ErrorHelper::getNotFoundError('AddressBook');
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
     * Saving new addressbook resource
     * @date 15-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  string $tenantId
     * @return a new created addressbook resource in json format.
     */
    /**
    * @SWG\Post(
    *   path="/tenants/{tenantId}/addressbooks",
    *	tags={"AddressBook"},
    *   summary="save a new addressbook",
    *   operationId="saveAddressBook",
    *   produces={"application/json"},
    *	 @SWG\Parameter(  
    *     name="tenantId",  
    *     in="path",  
    *     description="id of tenant to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="data",  
    *     in="body",  
    *     description="provide your addressbook data information to save",  
    *     required=true,
    *	   @SWG\Schema(type="object")
    *   ),
    *   @SWG\Response(response=200, description="return a new saved addressbook in json format"),
    *	 security={
    *         {
    *             "default": {}
    *         }
    *     }
    * )
    *
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
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
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Render input and save new addressbook object
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
                $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId . '/addressbooks/'. $outputObj->_id;
                $result = $this->schema->renderOutput($outputObj,$href,$user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager($tenantId);
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New AddressBook', 
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
     * Updating addressbook resource by id
     * @date 15-Feb-2017
     * @author  Phou Lin <lin.phou@workevolve.com>
     * @param   string $tenantId
     * @param   string $addressBookId
     * @return  a new updated record in json format.
     */
    /**
    * @SWG\Post(
    *   path="/tenants/{tenantId}/addressbooks/{addressBookId}",
    *	tags={"AddressBook"},
    *   summary="update addressbook by id",
    *   operationId="updateAddressBook",
    *   produces={"application/json"},
    *	 @SWG\Parameter(  
    *     name="tenantId",  
    *     in="path",  
    *     description="id of tenant to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="addressBookId",  
    *     in="path",  
    *     description="id of addressbook to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="data",  
    *     in="body",  
    *     description="provide your addressbook data information to update",  
    *     required=true,
    *	   @SWG\Schema(type="object")
    *   ),
    *   @SWG\Response(response=200, description="return an updated addressbook resource in json format"),
    *	 security={
    *         {
    *             "default": {}
    *         }
    *     }
    * )
    *
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function update($tenantId,$addressBookId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            //if(isset($this->data["type"])){ unset($this->data["type"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Get addressbook object by id for updating
                $criteria = new CriteriaOption();
                $criteria->where(AddressBook::FIELD_TENANT, $tenantId);
                $criteria->where('_id', $addressBookId);
                $this->me->pushCriteria($criteria);
                $this->me->applyCriteria();
                $updateObj = $this->me->readOne($this->schema->getAttributes());
                if($updateObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canChange($updateObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    //Get addressbook object by id for value-relationship
                    $oldObj = $this->me->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/addressbooks/'. $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update addressbook object
                    $obj = $this->schema->renderInput($this->data, $updateObj, $user, "U");
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
                    $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/addressbooks/'. $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying AddressBook', 
                                            $user
                                        );
                    $revisionObj->save($eventId,
                                        $this->schema->getAllFields(),
                                        $oldTargetObj,
                                        $result,
                                        'Update',
                                        $this->schema->getPluralName(),
                                        $user);
                    //Return result of updating address book in JSON format
                    return response()->json($result);
                }else{
                    return ErrorHelper::getNotFoundError('AddressBook');
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
     * Removing addressbook resource by id
     * @date 15-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  string $tenantId
     * @param  string $addressBookId
     * @return -
     */
    /**
    * @SWG\Delete(
    *   path="/tenants/{tenantId}/addressbooks/{addressBookId}",
    *	tags={"AddressBook"},
    *   summary="delete addressbook by id",
    *   operationId="removeAddressBookById",
    *   produces={"application/json"},
    *	 @SWG\Parameter(  
    *     name="tenantId",  
    *     in="path",  
    *     description="id of tenant to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="addressBookId",  
    *     in="path",  
    *     description="id of addressbook to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *   @SWG\Response(response=200, description="return empty array if delete operation is success"),
    *	 security={
    *         {
    *             "default": {}
    *         }
    *     }
    * )
    *
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function remove($tenantId,$addressBookId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where(AddressBook::FIELD_TENANT, $tenantId);
            $criteria->where('_id', $addressBookId);
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
                    $href = env('APP_URL') .env('APP_VERSION'). '/tenants/' . $tenantId .'/addressbooks/'. $targetObj->_id;
                    $tObj = $this->schema->renderOutput($targetObj, $href, $user);
                    $relationshipObj->remove($this->schema->getAllFields(), 
                                                $tObj, 
                                                $this->schema->getPluralName()
                                            );
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Deleting AddressBook', 
                                            $user
                                        );
                    $revisionData[] = $revisionObj->getRevision($eventId, 
                                            $this->schema->getPluralName(), 
                                            $addressBookId, 
                                            null, 
                                            null, 
                                            null, 
                                            'Delete');
                    $revisionObj->insertData($revisionData, $user);
                    if($this->me->remove()){ return Response()->json(array())->setStatusCode(200); }
                }else{
                    return ErrorHelper::getInUsedError("AddressBook");
                }
            }else{
                return ErrorHelper::getNotFoundError("AddressBook");
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
