<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Tenant\Tenant;
use App\Http\Controllers\User\User;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Udc\UdcValueFoundation;
use App\Http\Controllers\CognitoAws\CognitoAws;
use App\Tenant as TN;
use Illuminate\Support\Facades\Input;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Helper\ErrorHelper;

/**
 * Resful API for tenants
 * @Author Seng Sathya
 * To retrieve, store, update, delete tenant  
 */
class TenantController extends Controller
{
    private $me;
    private $data;
    private $schema;
    private $schemaTable = "TTENANT";
    private $awsCognito;
    
    //Initailize constructor of tenant controller
    public function __construct(Request $request){
        $this->data = $request->all();
        if(isset($this->data["schema"]) && $this->data["schema"] != ""){
            $this->schemaTable = $this->data["schema"];
        }
        $this->me = new Tenant();
        $this->schema = new SchemaRender($this->schemaTable);
        $this->awsCognito = new CognitoAws();
    }
    /**
     * register new tenant (NEW ACCOUNT) to database
     * @date 06-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return newly created tenant json object
     */
    public function register(){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $validateSMS = $this->schema->validate($this->data);
            if($validateSMS["status"]){
                //Save info of tenant to collection tenants
                $obj  =   $this->schema->renderInput($this->data, $this->me->getModel(), $user, "C");
                $tenantObj = $this->me->save($obj);
                //Prepare setup data initialization

                //PROCESS DATA INITIALIZATION (UDC, MENU, OBJECT LIBRARY, ROLE, 
                //                             PERMISSION, SECURITY, TEMPLATE, TIMEZONE, etc)


                //Prepare tenant data to save into collection user
                $tenantEmbeded = array(
                    'id' => $tenantObj->id,
                    'name' => $tenantObj->name,
                    'apps' => array(array(
                        'app' => UdcValueFoundation::getUdcValue(Tenant::VALUE_SYS_CODE,
                                                                 Tenant::TYPE_APP,
                                                                 Tenant::VALUE_APP_PROJ),
                        'envs' => array(array(
                            'env' => UdcValueFoundation::getUdcValue(Tenant::VALUE_SYS_CODE,
                                                                     Tenant::TYPE_ENV,
                                                                     Tenant::VALUE_ENV_PROD),
                            'status' => UdcValueFoundation::getUdcValue(
                                                                Tenant::VALUE_SYS_CODE,
                                                                Tenant::TYPE_STATUS,
                                                                Tenant::VALUE_USR_ACTIVE),
                            'user_type' =>  UdcValueFoundation::getUdcValue(
                                                                Tenant::VALUE_SYS_CODE,
                                                                Tenant::TYPE_USR,
                                                                Tenant::VALUE_EMPLOYEE)
                        ))
                    ))
                );
                //Save user account to collection users
                if(isset($user->tenants) && $user->tenants->isEmpty()){
                    $userObj = new User();
                    $userSchema = new SchemaRender('TUSER');
                    //Getting user object for updating information
                    $criteria = new CriteriaOption();
                    $criteria->where('_id',$user->_id);
                    $userObj->pushCriteria($criteria);
                    $userObj->applyCriteria();
                    $updatedObj = $userObj->readOne();
                    //Prepare data for update
                    $this->data['firstName'] = $user->awsCognito["firstName"];
                    $this->data['lastName'] = $user->awsCognito["lastName"];
                    $this->data['email'] = $user->email;
                    $this->data['status'] = Tenant::VALUE_USR_ACTIVE;
                    $this->data['contactEmail'] = array([
                                                    'type' => Tenant::VALUE_CONT_EMAIL,
                                                    'value' => $user->email
                                                ]);
                    $this->data['tenants'] = array($tenantEmbeded);
                    $obj = $userSchema->renderInput($this->data, $updatedObj, $user, "C");
                    $result = $userObj->save($obj);
                    $href = env('APP_URL') .env('APP_VERSION').'/users/' . $result->_id;
                    $arrUser = $userSchema->renderOutput($result,$href,$user);
                    $userCtrlObj = new UserController(new Request());
                    $arrUser['tenants'] = $userCtrlObj->renderTenant($result);
                    return response()->json($arrUser);
                }else{
                    //Update user account for new tenant with existing account
                    $user->push('tenants',array($tenantEmbeded));
                    $userObj = new User();
                    $userSchema = new SchemaRender('TUSER');
                    $criteria = new CriteriaOption();
                    $criteria->where('_id',$user->_id);
                    $userObj->pushCriteria($criteria);
                    $userObj->applyCriteria();
                    $outputObj = $userObj->readOne();
                    if($outputObj){
                        $href = env('APP_URL') .env('APP_VERSION').'/users/' . $outputObj->_id;
                        $arrUser = $userSchema->renderOutput($outputObj,$href,$outputObj);
                        $userCtrlObj = new UserController(new Request());
                        $arrUser['tenants'] = $userCtrlObj->renderTenant($outputObj);
                        return response()->json($arrUser);
                    }else{
                        return ErrorHelper::getNotFoundError('User');
                    }
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
     * list a tenant info from database
     * @date 06-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return json of a tenant info
     */
    public function readOne($tenantId){
        try{
            //Access Token Validation
            $user = $this->awsCognito->validateAccessToken();
            $criteria = new CriteriaOption();
            $criteria->where('_id', $tenantId);
            $this->me->pushCriteria($criteria);
            $this->me->applyCriteria();
            $tenantObj = $this->me->readOne($this->schema->getAttributes());
            if($tenantObj){
                $href = env('APP_URL') .env('APP_VERSION').'/tenants/' . $tenantObj->_id;
                return response()->json($this->schema->renderOutput($tenantObj, $href, $user));
            }else{
                return ErrorHelper::getNotFoundError('Tenant');
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
     * Tenant subscribe pricing plan using stripe payment method
     * @date 12-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return boolean
     */
    public function subscribe($tenantId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $token  = Input::get("stripeToken");
            $tn     = TN::Where('_id',$tenantId)->first();
            if(!$tn->subscribed('PT')){
                //Update billing information if any
                $tn->billing = [
                    'address_line1'     => Input::get("address_line1"),
                    'address_line2'     => Input::get("address_line2"),
                    'address_zip'       => Input::get("address_zip"),
                    'address_city'      => Input::get("address_city"),
                    'address_state'     => Input::get("address_state"),
                    'address_country'   => Input::get("address_country")
                ];
                $tn->save(); //Save billing information
                //Subscribe new user to plan in stripe
                $tn->newSubscription('PT', Input::get("stripePlan"))->create($token);
            }
            //swap subscription plan and add new credit card
            else{
                //Update credit card
                $tn->updateCard($token);
                //Update card back to our database
                $tn->updateCardFromStripe();
                //changePlan;
                $tn->subscription('PT')->swap(Input::get("stripePlan"));
            }
            return response()->json(true);
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
     * Check subscription status of tenant based on id
     * @date 14-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId
     * @return boolean
     */
    public function checkSubscriptionStatus($tenantId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $tn = TN::Where('_id',$tenantId)->first();
            return response()->json($tn->subscribed('PT') && ($tn->card_brand != null));
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
     * Change subscription plan (Downgrade/Upgrade)
     * @date 14-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId
     * @return boolean
     */
    public function changePlan($tenantId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $tn = TN::Where('_id',$tenantId)->first();
            $tn->subscription('PT')->swap(Input::get("stripePlan"));
            return response()->json(true);
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
     * Get payment methods information
     * @date 15-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId
     * @return json of payment methods information
     */
    public function getPaymentMethods($tenantId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $tn = TN::Where('_id',$tenantId)->first();
            if(isset($tn->stripe_id) && $tn->stripe_id != null){
                return response()->json($tn->asStripeCustomer());
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
     * Add payment methods (credit card) information
     * @date 16-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId
     * @return json of payment methods information
     */
    public function addPaymentMethods($tenantId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $token  = Input::get("stripeToken");
            $tn     = TN::Where('_id',$tenantId)->first();
            if(isset($tn->stripe_id) && $tn->stripe_id != null){
                //Update or add new credit card
                $tn->updateCard($token);
            }else{
                //Create customer with new credit card 
                $tn->createAsStripeCustomer($token);
            }
            //Update card back to our database
            $tn->updateCardFromStripe();
            return response()->json($tn->asStripeCustomer());
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
     * Delete payment methods (credit card) information
     * @date 19-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId, cardId
     * @return json of payment methods information has been delete
     */
    public function removePaymentMethods($tenantId, $cardId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $tn = TN::Where('_id',$tenantId)->first();
            if(isset($tn->stripe_id) && $tn->stripe_id != null){
                $customer = $tn->asStripeCustomer();
                $result = $customer->sources->retrieve($cardId)->delete();
                //Update card back to our database
                $tn->updateCardFromStripe();
                return response()->json($result);
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
     * Make default payment methods (credit card) information
     * @date 19-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId, cardId
     * @return updated json of payment methods
     */
    public function makeDefaultPaymentMethods($tenantId, $cardId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $tn = TN::Where('_id',$tenantId)->first();
            if(isset($tn->stripe_id) && $tn->stripe_id != null){
                $customer = $tn->asStripeCustomer();
                $customer->default_source = $cardId;
                $result = $customer->save();
                //Update card back to our database
                $tn->updateCardFromStripe();
                return response()->json($result);
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
     * Update payment methods (credit card) information
     * @date 19-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId, cardId
     * @return updated json of payment methods
     */
    public function updatePaymentMethods($tenantId, $cardId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
            $tn = TN::Where('_id',$tenantId)->first();
            if(isset($tn->stripe_id) && $tn->stripe_id != null){
                $customer = $tn->asStripeCustomer();
                $card = $customer->sources->retrieve($cardId);
                $card->name = Input::get("name");
                $card->exp_month = Input::get("exp_month");
                $card->exp_year = Input::get("exp_year");
                $card->address_line1 = Input::get("address_line1");
                $card->address_line2 = Input::get("address_line2");
                $card->address_zip = Input::get("address_zip");
                $card->address_city = Input::get("address_city");
                $card->address_state = Input::get("address_state");
                $card->address_country = Input::get("address_country");

                return response()->json($card->save());
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
     * Get billing invoice from stripe
     * @date 20-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId
     * @return billing json from stripe
     */
    public function getBilling($tenantId){
        try{
            //Validate access_token
            $user = $this->awsCognito->validateAccessToken();
        
            $tn = TN::Where('_id',$tenantId)->first();
            if(isset($tn->stripe_id) && $tn->stripe_id != null){
                $invoices = $tn->invoices()->map(function($invoice) {
                    //var_dump($invoice->asStripeInvoice());        //Public function to get invoice object
                    \Stripe\Stripe::setApiKey(env("STRIPE_SECRET"));
                    //getting charge object
                    if(isset($invoice->asStripeInvoice()->charge)){
                        $chargeObj  = \Stripe\Charge::retrieve($invoice->asStripeInvoice()->charge);
                        $card_brand = $chargeObj->source->brand;
                        $card_last_4 = $chargeObj->source->last4;
                    }else{
                        $card_brand = "";
                        $card_last_4 = "";
                    }
                    //data needed to show billing to subscriber
                    return [
                        'id'        => $invoice->id,
                        'date'      => $invoice->date()->toFormattedDateString(),
                        'total'     => $invoice->total(),
                        'discount'  => $invoice->discount(),
                        'coupon'    => $invoice->coupon(),
                        'card_brand' => $card_brand,
                        'card_last_four' => $card_last_4
                    ];
                });
            }else{
                $invoices = [];
            }
            return [
                    'count' => $invoices->count(),
                    'invoices' => $invoices 
                ];
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
     * Download PDF invoice
     * @date 20-Jun-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  tenantId
     * @return PDF File
     */
    public function downloadInvoice($tenantId){
        try{
            //Validate access_tokens
            $user = $this->awsCognito->validateAccessToken();
            $tn = TN::Where('_id',$tenantId)->first();
            $invoiceId = Input::get("id");
            $product = Input::get("product");
            $billAddress = "Bill to Address:" . $tn->org_name . "," . $tn->billing["address_line1"] . ", " .
                    $tn->billing["address_country"] . ", " . $tn->billing["address_zip"] . ", " .
                    $tn->billing["address_city"] . ", " . $tn->billing["address_state"];

            if(isset($tn->stripe_id) && $tn->stripe_id != null){
                return $tn->downloadInvoice($invoiceId, [
                    'vendor'  => $billAddress,
                    'product' => $product,
                ]);
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
