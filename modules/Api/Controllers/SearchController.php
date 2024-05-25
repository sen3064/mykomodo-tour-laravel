<?php
namespace Modules\Api\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Booking\Models\Service;
use Modules\Flight\Controllers\FlightController;
use Illuminate\Support\Facades\Auth;
use App\User;
use Illuminate\Support\Facades\DB;
use App\Models\ShopSetting;

class SearchController extends Controller
{

    public function search($type = 'tour'){
        $type = $type ? $type : request()->get('type');
        $filters = [];
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }
        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            // return $this->sendError(__("Type does not exists"));
            $class = "Modules\Tour\Models\Tour";
        }
        
        $rows = call_user_func([$class,'search'],request());
        $rowsm = [];
        // dd($rows);
        
        $business_names = [];
        $c=0;
        $unsets=[];
        foreach($rows[0] as &$row0){
            // dd([$rows,$row0]);
            // if(sizeof($row0)>0){
            // $row = $row0[0];
            $row = $row0;
            // $getSetting = DB::table('shop_settings')->where(['user_id'=>$row->create_user,'object_model'=>'tour'])->first();
            $getSetting = ShopSetting::where(['user_id'=>$row->create_user,'object_model'=>'tour'])->first();
            $is_open = !$getSetting ? true : ($getSetting->is_open==1 ? true:false);
            if(!$is_open){
                array_push($unsets,$c);
                $c++;
                continue;
            }
            if(!array_key_exists($row->create_user,$business_names)){
                if($getSetting){
                    $business_names[$row->create_user] = $getSetting->name;
                }else{
                    $business_names[$row->create_user] = User::find($row->create_user)->business_name;
                }
            }
            $row->business_name = $business_names[$row->create_user];
            $row->seller = User::find($row->create_user,['id','first_name','last_name','name as pic_name','email']);
            $c++;
                
            array_push($rowsm,$row);
            // }
        }
        // $total = $rows->total();
        foreach($unsets as $k => $v){
            unset($rowsm[$v]);
        }
        $total = sizeof($rowsm);

        return $this->sendSuccess(
            [
                'total'=>$total,
                // 'total_pages'=>$rows->lastPage(),
                // 'data'=>$rows->map(function($row){
                //     return $row->dataForApi();
                // }),
                'data'=>$rowsm,
            ]
        );
    }

    public function myProducts(){
        $type = 'tour';
        $type = $type ? $type : request()->get('type');
        $filters = [];
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }

        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            // return $this->sendError(__("Type does not exists"));
            $class = "Modules\Tour\Models\Tour";
        }
        
        $rows = call_user_func([$class,'myProducts'],request());
        // $total = $rows->total();
        $total = sizeof($rows);

        return $this->sendSuccess(
            [
                'total'=>$total,
                // 'total_pages'=>$rows->lastPage(),
                // 'data'=>$rows->map(function($row){
                //     return $row->dataForApi();
                // }),
                'data'=>$rows,
            ]
        );
    }


    public function searchServices(){
        $rows = call_user_func([new Service(),'search'],request());
        $total = $rows->total();
        return $this->sendSuccess(
            [
                'total'=>$total,
                'total_pages'=>$rows->lastPage(),
                'data'=>$rows->map(function($row){
                    return $row->dataForApi();
                }),
            ]
        );
    }

    public function getFilters($type = ''){
        $type = $type ? $type : request()->get('type');
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }
        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            // return $this->sendError(__("Type does not exists"));
            $class = "Modules\Tour\Models\Tour";
        }
        $data = call_user_func([$class,'getFiltersSearch'],request());
        return $this->sendSuccess(
            [
                'data'=>$data
            ]
        );
    }

    public function getFormSearch($type = ''){
        $type = $type ? $type : request()->get('type');
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }
        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            // return $this->sendError(__("Type does not exists"));
            $class = "Modules\Tour\Models\Tour";
        }
        $data = call_user_func([$class,'getFormSearch'],request());
        return $this->sendSuccess(
            [
                'data'=>$data
            ]
        );
    }

    public function detail($id = '')
    {
        $type = 'tour';
        if(empty($type)){
            return $this->sendError(__("Resource is not available"));
        }
        if(empty($id)){
            return $this->sendError(__("Resource ID is not available"));
        }

        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            // return $this->sendError(__("Type does not exists"));
            $class = "Modules\Tour\Models\Tour";
        }

        $row = $class::find($id);
        if(empty($row))
        {
            return $this->sendError(__("Resource not found"));
        }

        if($type=='flight'){
            return (new FlightController())->getData(\request(),$id);
        }

        return $this->sendSuccess([
            'data'=>$row->dataForApi(true)
        ]);

    }

    public function checkAvailability(Request $request ,$id = ''){
        $type = 'tour';
        if(empty($type)){
            return $this->sendError(__("Resource is not available"));
        }
        if(empty($id)){
            return $this->sendError(__("Resource ID is not available"));
        }
        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            // return $this->sendError(__("Type does not exists"));
            $class = "Modules\Tour\Models\Tour";
        }
        $classAvailability = $class::getClassAvailability();
        $classAvailability = new $classAvailability();
        // dd($classAvailability);
        $request->merge(['id' => $id]);
        if($type == "hotel"){
            $request->merge(['hotel_id' => $id]);
            return $classAvailability->checkAvailability($request);
        }
        return $classAvailability->loadDates($request);
    }

    public function checkBoatAvailability(Request $request ,$id = ''){
        if(empty($id)){
            return $this->sendError(__("Boat ID is not available"));
        }
        $class = get_bookable_service_by_id('boat');
        $classAvailability = $class::getClassAvailability();
        $classAvailability = new $classAvailability();
        $request->merge(['id' => $id]);
        return $classAvailability->availabilityBooking($request);
    }
}
