<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\PostLibrary\Rate;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\User;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Relationship\RelationshipManager;

class RateController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TRATING";
    private $schemaVote = "TVOTE";
    private $awsCognito;
    
    //Initailize constructor of object library controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Rate(Route::current()->parameter('tenantId'),
                            Route::current()->parameter('resource'),
                            Route::current()->parameter('resourceId'));
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**  Use to get vote average
     * @date 29-Aug-2016
     * @author Seng Sathya <seng.sathya@workevolve.com>
     * @param $ratings
     * @return float 
     */
    private function getVoteAverage($ratings){
        $countVote    =   count($ratings->replies);  
        $sumVote      = 0;
        foreach($ratings->replies as $vote){
            $sumVote = $sumVote + (float) $vote['vote'];
        }
        return $sumVote/$countVote;
    }
    /**
     * Save rating of resource to database
     * @date 26-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @return newly created rating json
     */
    public function create($tenantId, $resource, $resourceId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            if(isset($this->data["id"])){ unset($this->data["id"]); }
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    /*
                    * check if resource is rate for the first time or more than one time
                    * if resource is rate for the first time the field $resource->_rating is null
                    */
                    $obj = $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                    if(isset($dataObj->_rating) && !empty($dataObj->_rating)){
                        $checkRateObj = new Rate($tenantId, $resource, $resourceId);
                        $criteria = new CriteriaOption();
                        $criteria->where(Rate::FIELD_TENANT, $checkRateObj->getTenantId());
                        $criteria->where('_id', $dataObj->_rating);
                        $criteria->where('replies.created_by', $user->_id);
                        $checkRateObj->pushCriteria($criteria);
                        $checkRateObj->applyCriteria();
                        $ratedUser = $checkRateObj->count();
                        /*
                        * check if user already voted on the resource then 
                        * we can not let them vote again . (this version)
                        */
                        if($ratedUser == 0){
                            $ct = new CriteriaOption();
                            $ct->where(Rate::FIELD_TENANT, $this->me->getTenantId());
                            $ct->where('_id', $dataObj->_rating);
                            $this->me->pushCriteria($ct);
                            $this->me->applyCriteria();
                            $rateObj = $this->me->readOne();
                            //Save voting in posts collection
                            $voteOutput = $rateObj->replies()->save($obj);
                            //Update total vote and voter in resource
                            $dataObj->total_rate = $this->getVoteAverage($rateObj);
                            $dataObj->total_raters = count($rateObj->replies);
                            $dataObj->save();
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/' . $resource . '/'. $resourceId . "/ratings/" . $voteOutput->_id;
                            return response()->json($this->schema->renderOutput($voteOutput,$href,$user));
                        }else{
                            return ErrorHelper::getAlreadyVotingError();
                        }
                    }else{
                        /*
                        * if the resource is vote for the first time
                        * we create new post and type is rating
                        * after we create post we insert field _rating into the resource
                        */
                        //Saving vote reference of resource with "TVOTE" schema
                        $vote = new Rate($tenantId, $resource, $resourceId);
                        $voteSchema = new SchemaRender($this->schemaVote);
                        $voteData = array(
                            'tenant' => $vote->getTenantId(),
                            'type' => $vote->getType(),
                            'resource' => $resource,
                            'resourceId' => $resourceId
                        );
                        $vObj = $voteSchema->renderInput($voteData, $vote->getModel(), $user, "C");
                        $voteOutput = $vote->save($vObj);
                        //Save rating value in reply of posts collection
                        $result = $voteOutput->replies()->save($obj);
                        //Update back resource object with total rate and rater
                        $dataObj->_rating = (string) $voteOutput->_id;
                        $dataObj->total_rate   = $this->data["vote"];
                        $dataObj->total_raters = 1;
                        $dataObj->save();
                        //Saving value-relationship data
                        $relationshipObj = new RelationshipManager($tenantId);
                        $relationshipObj->save($voteSchema->getAllFields(), 
                                            $voteData, 
                                            $voteOutput, 
                                            $voteSchema->getPluralName(), 
                                            $user);
                        //Relationship of resource with vote
                        $relData[] = $relationshipObj->getRelationship(
                                                $voteSchema->getPluralName(), 
                                                $voteOutput->_id,
                                                Rate::FIELD_RESOURCE_ID, 
                                                $resource, 
                                                $resourceId,
                                                "_id");
                        $relationshipObj->insertData($relData, $user);
                        //Render output and return created resource in json
                        $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource . "/" . $resourceId . "/ratings/" . $result->_id;
                        return response()->json($this->schema->renderOutput($result,$href,$user));
                    }
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
     * Update rating of resource to database
     * @date 26-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @param  string $resource
     * @param  string $resourceId
     * @param  string $userId
     * @return newly updated rating json
     */
    public function update($tenantId, $resource, $resourceId, $userId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                $dataObj = $this->me->getResource();
                if($dataObj){
                    /*
                    * check if resource is rate for the first time or more than one time
                    * if resource is rate for the first time the field $resource->_rating is null
                    */
                    if(isset($dataObj->_rating) && !empty($dataObj->_rating)){
                        $checkRateObj = new Rate($tenantId, $resource, $resourceId);
                        $criteria = new CriteriaOption();
                        $criteria->where(Rate::FIELD_TENANT, $checkRateObj->getTenantId());
                        $criteria->where('_id', $dataObj->_rating);
                        $criteria->where('replies.created_by',$user->_id);
                        $checkRateObj->pushCriteria($criteria);
                        $checkRateObj->applyCriteria();
                        $ratedUser = $checkRateObj->readOne();
                        /*
                        * check if user already voted on the resource then 
                        * we can update it
                        */
                        if(count($ratedUser) > 0){
                            $updatedObj = $ratedUser->replies()->where('created_by', $userId)->first();
                            $obj = $this->schema->renderInput($this->data, $updatedObj,$user, "U");
                            //Update voting in posts collection
                            $voteOutput = $ratedUser->replies()->save($obj);
                            //Update total vote and voter in resource
                            $dataObj->total_rate = $this->getVoteAverage($ratedUser);
                            $dataObj->total_raters = count($ratedUser->replies);
                            $dataObj->save();
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/'. $resource .'/'. $resourceId . "/ratings/" . $voteOutput->_id;
                            return response()->json($this->schema->renderOutput($voteOutput,$href,$user));
                        }else{
                            return ErrorHelper::getNotFoundError('Rating');
                        }
                    }else{
                        return ErrorHelper::getNotFoundError("Rating");
                    }
                }else{
                    return ErrorHelper::getNotFoundError($resource);
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
    /**  Use to retrieve one rating of resource by user id
     * @date 27-Jul-2017
     * @author Seng Sathya <seng.sathya@workevolve.com>
     * @param $tenantId
     * @param $resource
     * @param $resourceId
     * @param $userId
     * @return json $rating
     */
    public function readOne($tenantId, $resource, $resourceId, $userId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $dataObj = $this->me->getResource();
            if($dataObj){
                if(isset($dataObj->_rating) && !empty($dataObj->_rating)){
                    $ct = new CriteriaOption();
                    $usrObj = new User();
                    $ct->where('_id', $userId);
                    $usrObj->pushCriteria($ct);
                    $usrObj->applyCriteria();
                    $getUser = $usrObj->readOne();
                    if($getUser){
                        $checkRateObj = new Rate($tenantId, $resource, $resourceId);
                        $criteria = new CriteriaOption();
                        $criteria->where(Rate::FIELD_TENANT, $checkRateObj->getTenantId());
                        $criteria->where('_id', $dataObj->_rating);
                        $criteria->where('replies.created_by',$getUser->_id);
                        $checkRateObj->pushCriteria($criteria);
                        $checkRateObj->applyCriteria();
                        $ratedUser = $checkRateObj->readOne();
                        if($ratedUser){
                            $outputObj = $ratedUser->replies()->where('created_by',$getUser->_id)->first();
                            $href = env('APP_URL') .env('APP_VERSION').'/tenants/'. $tenantId . '/' . $resource . '/'. $resourceId . "/ratings/" . $outputObj->_id;
                            return response()->json($this->schema->renderOutput($outputObj,$href,$user));
                        }else{
                            return ErrorHelper::getNotFoundError('Rating');
                        }
                    }else{
                        return ErrorHelper::getNotFoundError("User");
                    }
                }else{
                    return ErrorHelper::getNotFoundError("Rating");
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
