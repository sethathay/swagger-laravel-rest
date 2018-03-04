<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Http\Controllers\Helper\ErrorHelper;

class LookUpController extends Controller
{
    private $me;
    private $data;
    private $awsCognito;
    
    //Initailize constructor
    public function __construct(Request $request){
        $this->data = $request->all();
        $this->me = new Schema();
        $this->awsCognito = new CognitoAws();
    }
    /**
     * Getting lookup datasource by datasource name
     * @date 14-Aug-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $tenantId
     * @return lookup datasource
     */
    public function readAll($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            //Validate request body (require datasource name)
            $validator = Validator::make($this->data,[ 
                'datasource' => 'required'
            ]);
            if($validator->fails()){ return ErrorHelper::getRequestBodyError(); }
            $criteria = new CriteriaOption();
            $criteria->where(Schema::FIELD_RECORD_TYPE, $this->me->getRecordType());
            $criteria->where(Schema::FIELD_OBJECT_TYPE, $this->me->getType());
            $criteria->where('datasource', $this->data['datasource']);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $data = $this->me->readOne();
            if($data){
                $dataMparams = [];
                // get request params 
                if(isset($this->data['mParams'])){
                    $originalMparams = $this->data['mParams'];
                    // format the data that retrieved from mParams to input into controller
                    foreach ($originalMparams as $mParam){
                        $dataMparams[$mParam['key']] = $mParam['value'];
                    }
                }
                //Calling to object controller with method
                $controller = $data->crud['controller'];
                $function = $data->crud["r"]['function'];
                $ob = new Request();
                $ob->replace($this->data);
                $controllerObj = new $controller($ob);
                $params = [];
                /* loop to match the params of the read operation and the mParam
                 * that provide from the request data 
                 */
                if($data->crud["r"]['params'] != null){
                    foreach ($data->crud["r"]['params'] as $param){
                        $key = $param['key'];
                        // if the resource need tenantId
                        if($key == 'tenant'){
                            // assign tenant id 
                            $dataMparams["tenant"] = $tenantId;
                        }
                        if(!isset($dataMparams[$key])){
                            return ($key . " is required !");
                        }
                        array_push($params, $dataMparams[$key]);
                    }
                }
                return $controllerObj->callAction($function, $params);
            }else{
                return ErrorHelper::getNotFoundError('DataSource');
            }
        }catch (\InvalidArgumentException $ex){
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
