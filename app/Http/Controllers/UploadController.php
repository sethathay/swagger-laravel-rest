<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Helper\ErrorHelper;

class UploadController extends Controller
{
    private $data;
    private $awsCognito;
    
    //Initailize constructor of object library controller
    public function __construct(Request $request){
        $this->data = $request->all();
        $this->awsCognito = new CognitoAws();
    }
    /**
     * upload file to temporary folder on aws s3
     * @date 09-27-2016
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @param string $tenantId id of tenant
     * @return json $fileInfo
     */
    public function upload($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Validate tenant of user request
            $tenantInUser =  $user->tenants()->where('id', $tenantId)->first(); 
            if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
                
            //Validate weCloudApp of user request
            $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
            if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}
            
            if(isset($this->data["file"])){
                //Get name of file
                $filename = $this->data['file']->getClientOriginalName();
                //Generate unique name base on tenant id , file name and time
                $unique_name = md5($tenantId.$filename.time());
                
                $s3 =   \Storage::disk('s3');
                $ext = '.' . end((explode(".", $filename)));
                //Path for upload to s3 temporary folder
                $filepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$unique_name . $ext;
                if(isset($this->data['temporary'])){
                    if($this->data['temporary'] == "false"){
                        //Path for upload to s3 main folder
                        $filepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$unique_name . $ext;
                    }else if($this->data['temporary'] != "true"){
                        return ErrorHelper::getUrlParamValueError('temporary');
                    } 
                }
                //Start upload to s3
                $s3->put($filepathAndName, 
                         file_get_contents($this->data["file"]->getPathName()),
                         'public'
                        );
                $fileInfo['name'] = $filename;
                $fileInfo['path'] = $unique_name . $ext;
                return response()->json($fileInfo);
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
     * upload file to temporary folder on aws s3
     * @date 09-27-2016
     * @author Seng Sathya <sathya.seng@workevolve.com>
     * @param string $tenantId id of tenant
     * @return json $fileInfo
     */
    public function remove($tenantId,$fileId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Validate tenant of user request
            $tenantInUser =  $user->tenants()->where('id',$tenantId)->first(); 
            if(empty($tenantInUser)){ return ErrorHelper::getForbiddenError();}
                
            //Validate weCloudApp of user request
            $weCloudApp = ErrorHelper::validateUserApp($tenantInUser);
            if(empty($weCloudApp)){ return ErrorHelper::getForbiddenError();}
            
            $s3     = \Storage::disk('s3');
            // path for upload to s3 temporary folder
            $filepathAndName = env('S3_BUCKETFOLDER_TEMP_ATTACHMENTS').$fileId;
            if(isset($this->data['temporary'])){
               if($this->data['temporary'] == "false"){
                    // path for upload to s3 main folder
                    $filepathAndName = env('S3_BUCKETFOLDER_ATTACHMENTS').$fileId;
                }else if($this->data['temporary'] != "true"){
                    return ErrorHelper::getUrlParamValueError('temporary');
                }  
            }

            // check if file exists
            if($s3->exists($filepathAndName)){
                if($s3->delete($filepathAndName)){
                    return "Delete Success";
                }else{
                    return "Delete Fail";
                }
            }else{
                return "File not found"; 
            }
            return false;
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