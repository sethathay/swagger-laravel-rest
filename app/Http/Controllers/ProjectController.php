<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Project\Project;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\AddressBook\Customer;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Relationship\RelationshipManager;
use App\Http\Controllers\Revision\RevisionManager;
use App\Http\Controllers\ACL\ACL;

class ProjectController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TPROJECT";
    private $awsCognito;
    
    //Initailize constructor of project controller
    public function __construct(Request $request){
        $this->data     =   $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me       =   new Project(Route::current()->parameter('tenantId'));
        $this->schema   =   new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Saving new project resource
     * @date 18-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @return newly created project resource in json
     */
    /**
    * @SWG\Post(
    *   path="/tenants/{tenantId}/projects",
    *	tags={"Project"},
    *   summary="save a new project",
    *   operationId="saveProject",
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
    *     description="provide your project data information to save",  
    *     required=true,
    *	   @SWG\Schema(type="object")
    *   ),
    *   @SWG\Response(response=200, description="return a new saved project in json format"),
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
            //Take out field totalRate and totalRaters
            if(isset($this->data["totalRate"])){ unset($this->data["totalRate"]); }
            if(isset($this->data["totalRaters"])){ unset($this->data["totalRaters"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();        
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Checking for existing of project resource for given no
                $criteria = new CriteriaOption();
                $pObject = new Project($tenantId);
                $criteria->where(Project::FIELD_TENANT, $pObject->getTenantId());
                $criteria->where('no', $this->data["no"]);
                $pObject->pushCriteria($criteria);
                $pObject->applyCriteria();
                $findObj = $pObject->readOne();
                if(isset($findObj)){
                    $validator = Validator::make($this->data,[
                        'no' => 'unique:projects,no'
                    ]);
                    if ($validator->fails()){
                        return ErrorHelper::getUniqueValidationError($validator->errors()->first('no'));
                    }
                }
                //Render input and save new project object
                $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                $outputObj = $this->me->save($obj);
                //Query to get recently added item (Reason: key of project is no not id)
                $ct = new CriteriaOption();
                $pObj = new Project($tenantId);
                $ct->where(Project::FIELD_TENANT, $pObj->getTenantId());
                $ct->where('no', $outputObj->getKey());
                $pObj->pushCriteria($ct);
                $pObj->applyCriteria();
                $result = $pObj->readOne();
                //Saving value-relationship data
                $relationshipObj = new RelationshipManager($tenantId);
                $relationshipObj->save($this->schema->getAllFields(), 
                                        $this->data, 
                                        $result, 
                                        $this->schema->getPluralName(), 
                                        $user);
                //Render output and return created resource in json
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/projects/'. $result->_id;
                $arrOutput = $this->schema->renderOutput($result, $href, $user);
                //Saving audit trail and data versioning
                $revisionObj = new RevisionManager($tenantId);
                $eventId = $revisionObj->newEvent(
                                        'Event - Saving New Project', 
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
     * Updating a project resource by id
     * @date 19-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $projectId
     * @return updated project object json
     */
    /**
    * @SWG\Post(
    *   path="/tenants/{tenantId}/projects/{projectId}",
    *	 tags={"Project"},
    *   summary="update project by id",
    *   operationId="updateProject",
    *   produces={"application/json"},
    *	 @SWG\Parameter(  
    *     name="tenantId",  
    *     in="path",  
    *     description="id of tenant to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="projectId",  
    *     in="path",  
    *     description="id of project to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="data",  
    *     in="body",  
    *     description="provide your project data information to update",  
    *     required=true,
    *	   @SWG\Schema(type="object")
    *   ),
    *   @SWG\Response(response=200, description="return an updated project resource in json format"),
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
    public function update($tenantId,$projectId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            //Take out field totalRate and totalRaters
            if(isset($this->data["totalRate"])){ unset($this->data["totalRate"]); }
            if(isset($this->data["totalRaters"])){ unset($this->data["totalRaters"]); }
            //Add pre-defined fields to input data
            $this->data["tenant"] = $this->me->getTenantId();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Getting project by tenantId and projectId
                $criteria = new CriteriaOption();
                $pObj = new Project($tenantId);
                $criteria->where(Project::FIELD_TENANT,$this->me->getTenantId());
                $criteria->whereRaw($pObj->getDefaultFilter($projectId), $this->schema->getAllFields(), $user);
                $pObj->pushCriteria($criteria);
                $pObj->applyCriteria();
                $updatedObj = $pObj->readOne($this->schema->getAttributes());
                if($updatedObj){
                    $aclObj = new ACL($tenantId, $user, $this->schema);
                    if(!$aclObj->canChange($updatedObj)){
                        return ErrorHelper::getPermissionError();
                    }
                    if(isset($updatedObj) && $updatedObj->no != $this->data['no']){
                        $validator = Validator::make($this->data,[
                            'no' => 'unique:projects,no'
                        ]);
                        if ($validator->fails()){
                            return ErrorHelper::getUniqueValidationError($validator->errors()->first('no'));
                        }
                    }
                    //Get project object by id for value-relationship
                    $oldObj = $pObj->readOne($this->schema->getAttributes());
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/projects/'. $oldObj->_id;
                    $oldTargetObj = $this->schema->renderOutput($oldObj, $href, $user);
                    //Render input and update project object
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
                    $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/projects/'. $outputObj->_id;
                    $result = $this->schema->renderOutput($outputObj,$href,$user);
                    //Saving audit trail and data versioning
                    $revisionObj = new RevisionManager($tenantId);
                    $eventId = $revisionObj->newEvent(
                                            'Event - Modifying Project', 
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
                    return ErrorHelper::getNotFoundError("Project");
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
     * Get all project resources
     * @date 19-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @return list of projects resource json
     */
    /**
    * @SWG\Post(
    *   path="/tenants/{tenantId}/projects/get",
    *	 tags={"Project"},
    *   summary="get all projects",
    *   operationId="getAllProjects",
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
    *     description="filter projects by condition provided",  
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
    *		default=0
    *   ),
    *	 @SWG\Parameter(  
    *     name="limit",  
    *     in="query",  
    *     description="The number of items to return",  
    *     required=false,  
    *     type="integer",
    *     format="int32",
    *		default=25
    *   ),
    *   @SWG\Response(response=200, description="return all projects in json format"),
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
            //Check authorization of user for project data in tenant
//                $tenantInUser =  $user->tenants()->where('id',$this->me->getTenantId())->first();
//                if(empty($tenantInUser)) return ErrorHelper::getErrorUnauthorize();

            $criteria   =   new CriteriaOption();
            $criteria->where(Project::FIELD_TENANT, $this->me->getTenantId());
            $aclObj = new ACL($tenantId, $user, $this->schema);
            $aclFilters = $aclObj->getFiltersOfACL();
            if(count($aclFilters) > 0 ){
                $criteria->whereRaw($aclFilters,$this->schema->getAllFields(),$user);
            }
            if(isset($this->data['filter'])){
                $criteria->whereRaw($this->data['filter'],$this->schema->getAllFields(),$user);
            }
            //Get customers list
//                $customerIdList = Customer::getCurrentCustomerIdOfUser($tenantInUser);
//                if(!empty($customerIdList)) $criteria->whereIn('customer',$customerIdList);

//                if(isset($this->data['status']) && !empty($this->data['status'])){
//                    $criteria->whereIn('status.code',$this->data['status']);
//                }
//
//                if(isset($this->data['cid']) && $this->data['cid'] !=-1 && !empty($this->data['cid'])){
//                    $criteria->where('customer',$this->data['cid']);
//                }
//
//                if(isset($this->data['pno']) && !empty($this->data['pno'])){
//                    $criteria->where('no','LIKE','%' . $this->data['pno'] . '%');
//                }
//
//                if(isset($this->data['from']) && isset($this->data['to']) &&
//                   !empty($this->data['from']) && !empty($this->data['to'])){
//
//                }

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
            $dsHref = env("APP_URL").env("APP_VERSION") . "/tenants/" . $this->me->getTenantId() . "/projects";
            $ds =   $this->me->readAll($this->schema->getAttributes());
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
     * Get a project resource by id
     * @date 19-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $projectId
     * @return a project resource in json format
     */
    /**
    * @SWG\Get(
    *   path="/tenants/{tenantId}/projects/{projectId}",
    *	 tags={"Project"},
    *   summary="get project by id",
    *   operationId="getProjectById",
    *   produces={"application/json"},
    *	 @SWG\Parameter(  
    *     name="tenantId",  
    *     in="path",  
    *     description="id of tenant to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *	 @SWG\Parameter(  
    *     name="projectId",  
    *     in="path",  
    *     description="id of project to filter by",  
    *     required=true,  
    *     type="string"
    *   ),
    *   @SWG\Response(response=200, description="return a project resource in json format"),
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
    public function readOne($tenantId, $projectId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Getting project by tenantId and projectId
            $criteria = new CriteriaOption();
            $criteria->where(Project::FIELD_TENANT, $this->me->getTenantId());
            $criteria->whereRaw($this->me->getDefaultFilter($projectId), $this->schema->getAllFields(), $user);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $result = $this->me->readOne($this->schema->getAttributes());
            if($result){
                $aclObj = new ACL($tenantId, $user, $this->schema);
                if(!$aclObj->canRead($result)){
                    return ErrorHelper::getPermissionError();
                }
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $this->me->getTenantId() . '/projects/'. $result->_id;
                $arrProject = $this->schema->renderOutput($result, $href, $user);
                return response()->json($arrProject);
            }else{
                return ErrorHelper::getNotFoundError('Project');
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