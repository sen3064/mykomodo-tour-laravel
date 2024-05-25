<?php

namespace Modules\Api\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourParent;
use Modules\Tour\Models\TourDate;
use Modules\Tour\Models\BravoTourDate;
use Illuminate\Support\Facades\Http;
use Modules\Location\Models\Location;
use Modules\Media\Models\MediaFile;
use Carbon\Carbon;

class TourAPIController extends Controller
{
    private function generateSlug($uid, $title)
    {
        $slug = strtolower(str_replace(' ','-',$title)) . '-' . $uid . '-';
        $code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $slug .= $code;
        if (TourParent::where('slug', $slug)->doesntExist())
            return $slug;
        $this->generateSlug($uid, $title);
    }

    private function generateTourSlug($uid, $title)
    {
        $slug = strtolower(str_replace(' ','-',$title)) . '-' . $uid . '-';
        $code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $slug .= $code;
        if (Tour::where('slug', $slug)->doesntExist())
            return $slug;
        $this->generateTourSlug($uid, $title);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $slug = $this->generateTourSlug($user->id,$request->title);
        
        $this->token = $request->bearerToken();
        $cdn = "https://cdn.mykomodo.kabtour.com/v2/media_files";
        $post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
        $names = [];
        foreach ($request->allFiles() as $k => $v) {
            if(is_array($v)){
                foreach($v as $vk){
                    $name = $vk->getClientOriginalName();
                    $post->attach($k.'[]', file_get_contents($vk), $name);
                    $names[]=$name;
                }
            }else{
                $name = $v->getClientOriginalName();
                $post->attach($k, file_get_contents($v), $name);
                $names[]=$name;
            }
        }
        // dd($names);
        
        $response = $post->post($cdn, ["prefix" => $slug]);
        // dd($response->json());
        // dd($response->json());
        $result = json_decode(json_encode($response->json()));
        $banner = 0;
        $gallery = [];
        
        $data = '';

        $banner = $result->banner->id;
        if(isset($result->gallery)){
            for ($i = 0; $i < sizeof($result->gallery); $i++) {
                $gallery[] = $result->gallery[$i]->id;
            }
        }

        $tour = new Tour();
        $tour->title = $request->title;
        $tour->slug = $slug;
        $tour->content = $request->content;
        $tour->image_id = $banner;
        $tour->banner_image_id = $banner;
        $tour->gallery = implode(',', $gallery);
        $tour->location_id = Location::where('slug',$user->location)->first()->id;
        $tour->min_people = $request->min_people;
        $tour->max_people = $request->max_people;
        $tour->stock = $request->stock ?? 0;
        $tour->itinerary = $request->itinerary;
        $tour->is_private = $request->is_private;
        $tour->include = $request->include;
        $tour->duration = $request->duration;
        $tour->price = $request->price_weekday;
        $tour->price_weekend = $request->price_weekend;
        $tour->price_holiday = $request->price_holiday;
        $tour->status = $request->status;
        $tour->create_user = $user->id;
        $tour->save();

        if($request->holiday_price_status){
            if($request->has('dates')){
                $dates = $request->dates;
                if(!is_array($request->dates)){
                    $temp = str_replace('[','',$request->dates);
                    $temp = str_replace(']','',$request->dates);
                    $temp = str_replace(' ','',$request->dates);
                    $dates = explode(',',$temp);
                }
                for($i=0;$i<sizeof($dates);$i++){
                    BravoTourDate::updateOrCreate(
                        [
                            'target_id'=>$tour->id,
                            'price_date'=>$dates[$i]
                        ],
                        [
                            'active'=>$request->holiday_price_status ? 1 : 0,
                        ]
                    );
                }
                BravoTourDate::where('target_id',$tour->id)->whereNotIn('price_date',$dates)->update(['active'=>0]);
            }
            if($tour->price_holiday==0){
                BravoTourDate::where('target_id',$tour->id)->update(['active' => 0]);
            }
        }else{
            BravoTourDate::where('target_id',$tour->id)->update(['active' => 0]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $data
        ]);
    }

    public function update($id, Request $request){
        $data = '';
        $tour = Tour::find($id);
        if($tour){
            $data = $tour;
            $banner = $tour->image_id;
            $gallery = explode(',',$tour->gallery);

            $result = '';
            $this->token = $request->bearerToken();
            $cdn = "https://cdn.mykomodo.kabtour.com/v2/media_files";
            $post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
            $names = [];
            foreach ($request->allFiles() as $k => $v) {
                if(is_array($v)){
                    foreach($v as $vk){
                        $name = $vk->getClientOriginalName();
                        $post->attach($k.'[]', file_get_contents($vk), $name);
                        $names[]=$name;
                    }
                }else{
                    $name = $v->getClientOriginalName();
                    $post->attach($k, file_get_contents($v), $name);
                    $names[]=$name;
                }
            }
            if(sizeof($names)>0){
                $response = $post->post($cdn, ["prefix" => $tour->slug]);
                $result = json_decode(json_encode($response->json()));
            }
            if(isset($result->banner)){
                $banner = $result->banner->id;
            }
            if(isset($result->gallery)){
                for ($i = 0; $i < sizeof($result->gallery); $i++) {
                    $filename = $result->gallery[$i]->file_name;
                    $temp = explode('-',$filename);
                    $temp_name = $temp[sizeof($temp)-1];
                    $temp_num = explode('.',$temp_name)[0];
                    $gallery[(int)$temp_num] = $result->gallery[$i]->id;
                }
            }
            $tour->image_id = $banner;
            $tour->banner_image_id = $banner;
            $tour->gallery = implode(',',$gallery);
            $tour->updated_at = date('Y-m-d H:i:s');
            $tour->title = $request->title ?? $tour->title;
            $tour->content = $request->content ?? $tour->content;
            $tour->min_people = $request->min_people ?? $tour->min_people;
            $tour->max_people = $request->max_people ?? $tour->max_people;
            $tour->stock = $request->stock ?? $tour->stock;
            $tour->itinerary = $request->itinerary ?? $tour->itinerary;
            $tour->is_private = $request->is_private ?? $tour->is_private;
            $tour->include = $request->include ?? $tour->include;
            $tour->duration = $request->duration ?? $tour->duration;
            $tour->price = $request->price_weekday ?? $tour->price;
            $tour->price_weekend = $request->price_weekend ?? $tour->price_weekend;
            $tour->price_holiday = $request->price_holiday ?? $tour->price_holiday;
            $tour->status = $request->status ?? $tour->status;
            $tour->update_user = Auth::id();
            $tour->save();

            // $request->merge(['target_id'=>$tour->id]);
            // $this->setTourDates($tour->id,$request);
            if($request->holiday_price_status){
                if($request->has('dates')){
                    $dates = $request->dates;
                    if(!is_array($request->dates)){
                        $temp = str_replace('[','',$request->dates);
                        $temp = str_replace(']','',$request->dates);
                        $temp = str_replace(' ','',$request->dates);
                        $dates = explode(',',$temp);
                    }
                    for($i=0;$i<sizeof($dates);$i++){
                        BravoTourDate::updateOrCreate(
                            [
                                'target_id'=>$tour->id,
                                'price_date'=>$dates[$i]
                            ],
                            [
                                'active'=>$request->holiday_price_status ? 1 : 0,
                            ]
                        );
                    }
                    BravoTourDate::where('target_id',$tour->id)->whereNotIn('price_date',$dates)->update(['active'=>0]);
                }
                if($tour->price_holiday==0){
                    BravoTourDate::where('target_id',$tour->id)->update(['active' => 0]);
                }
            }else{
                BravoTourDate::where('target_id',$tour->id)->update(['active' => 0]);
            }

            
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diubah',
                'data' => $data
            ]);
        }

        return response()->json([
            'success'=>false,
            'message'=>'Data tidak ditemukan'
        ]);
    }

    public function setTourDates($target_id, Request $request){
        $arrStartDate = [];
        $user = Auth::user();
        if($request->status == '0'){
            TourDate::where('target_id',$target_id)->where('price',$request->price)->update(['active'=>0]);
            return response()->json([
                'success'=>true,
                'message'=>'Tour telah di non-aktifkan'
            ]);
        }
        if(!is_array($request->dates)){
            $request->dates = trim($request->dates);
            $request->dates = str_replace('[','',$request->dates);
            $request->dates = str_replace(']','',$request->dates);
            $request->dates = explode(',',$request->dates);
        }
        if(sizeof($request->dates)>0){
            foreach($request->dates as $k=>$v){
                $start_date = trim($v).' 00:00:00';
                $check = TourDate::where(['target_id'=>$target_id,'start_date'=>$start_date,'create_user'=>$user->id])->first();
                if($check){
                    $upd = TourDate::find($check->id);
                    $upd->active = $request->status;
                    $upd->price = $request->price;
                    $upd->updated_at = date('Y-m-d H:i:s');
                    $upd->update_user = $user->id;
                    $upd->save();
                }else{
                    $upd = new TourDate();
                    $upd->target_id = $target_id;
                    $upd->start_date = $start_date;
                    $upd->end_date = $start_date;
                    $upd->price = $request->price;
                    $upd->max_guests = Tour::find($target_id)->max_people;
                    $upd->active = $request->status;
                    $upd->create_user = $user->id;
                    $upd->created_at = date('Y-m-d H:i:s');
                    $upd->updated_at = date('Y-m-d H:i:s');
                    $upd->save();
                }
                $arrStartDate[]=$start_date;
            }
            TourDate::where('target_id',$target_id)->where('price',$request->price)->whereNotIn('start_date',$arrStartDate)->update(['active'=>0]);
            return response()->json([
                'success'=>true,
                'message'=>'Set tanggal tour berhasil'
            ]);
        }else{
            $today = date('Y-m-d',strtotime('+8 hours'));
            TourDate::where('target_id',$target_id)->where('price',$request->price)->whereRaw('substr(start_date,1,10) > ?',[$today])->update(['active'=>1]);
            return response()->json([
                'success'=>true,
                'message'=>'Tour berhasil diaktifkan'
            ]);
        }
    }
    
    public function getTourDates(Request $request) {
        $today = date('Y-m-d',strtotime('+8 hours'));
        $query = TourDate::select('target_id', 'price', 'start_date', 'active')->where('create_user', Auth::id());
        if($request->has('tour_id')){
            $query->where('target_id',$request->tour_id);
        }
        $query->whereRaw('substr(start_date,1,10) > ?',[$today]);
        $records = $query->get();

        $result = [];

        foreach ($records as $record) {
            $result[] = [
                'target_id' => $record->target_id,
                'price' => $record->price,
                'dates' => $record->active == 1 ? [Carbon::parse($record->start_date)->format('Y-m-d')] : [],
            ];
        }

        // Grouping by target_id and price
        $groupedResult = [];
        foreach ($result as $item) {
            $key = $item['target_id'] . '_' . $item['price'];
            if (!isset($groupedResult[$key])) {
                $groupedResult[$key] = [
                    'target_id' => $item['target_id'],
                    'price' => $item['price'],
                    'dates' => [],
                ];
            }

            $groupedResult[$key]['dates'] = array_merge($groupedResult[$key]['dates'], $item['dates']);
        }
        
        $avail = array_values($groupedResult);
        
        for($i=0;$i<sizeof($avail);$i++){
            $tour = Tour::find($avail[$i]['target_id']);
            $media = MediaFile::find($tour->image_id);
            $pre_url = 'https://cdn.mykomodo.kabtour.com/uploads/';
            $avail[$i]['banner']=[
                "original"=> $pre_url.$media->file_path,
                "200x150"=> $media->file_resize_200 != null ? $pre_url.$media->file_resize_200 : null,
                "250x200"=> $media->file_resize_250 != null ? $pre_url.$media->file_resize_250 : null,
                "400x350"=> $media->file_resize_400 != null ? $pre_url.$media->file_resize_400 : null,
            ];
            $avail[$i]['trip_type']=$tour->is_private;
        }
        // Membungkus hasil dengan "availability"
        $finalResult = [
            'tour_list' => Tour::where(['create_user'=>Auth::id(), 'status'=>'publish'])->pluck('title', 'id'),
            'availability' => $avail
        ];

        return response()->json($finalResult);
    }

    public function deleteTourDates($id,$price){
        TourDate::where('target_id',$id)->where('price',$price)->delete();
            return response()->json([
                'success'=>true,
                'message'=>'Data telah dihapus'
        ]);
    }

    public function delete($id){
        $tour = TourParent::find($id);
        if($tour){
            $tours = Tour::where('parent_id',$id)->get();
            foreach($tours as &$k){
                $k->delete();
            }
            $tour->delete();
            return response()->json([
                'success'=>true,
                'message'=>'Data telah dihapus'
            ]);
        }

        return response()->json([
            'success'=>false,
            'message'=>'Data tidak ditemukan'
        ]);
    }

    public function deleteTrip($id){
        $tour = Tour::find($id);
        if($tour){
            $tour->delete();
            TourDate::where('target_id',$id)->delete();
            return response()->json([
                'success'=>true,
                'message'=>'Data telah dihapus'
            ]);
        }
        return response()->json([
            'success'=>false,
            'message'=>'Data tidak ditemukan'
        ]);
    }
}
