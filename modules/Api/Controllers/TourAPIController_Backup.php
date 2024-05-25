<?php

namespace Modules\Api\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourParent;
use Illuminate\Support\Facades\Http;
use Modules\Location\Models\Location;

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
        // return response()->json([
        //     'success' => false,
        //     'message' => 'Produk berhasil ditambahkan',
        //     'data' => $request->all()
        // ]);
        $user = Auth::user();
        $slug = $this->generateSlug($user->id,$request->title);
        $open_trip = json_decode($request->open_trip);
        $private_trip = json_decode($request->private_trip) ?? null;
        $private_trip_count = 0;
        if($private_trip){
            $private_trip_count = sizeof($private_trip);
        }
        
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

        $parent = new TourParent();
        $parent->title = $request->title;
        $parent->slug = $slug;
        $parent->content = $request->content;
        $parent->image_id = $banner;
        $parent->gallery = implode(',', $gallery);
        $parent->location_id = Location::where('slug',$user->location)->first()->id;
        $parent->status = $request->status;
        $parent->create_user = $user->id;
        $parent->save();
        $data = $parent;
        // $data->variant = [];
        if($open_trip->is_active){
            $openTrip = new Tour();
            $openTrip->title = $open_trip->title;
            $openTrip->slug = $this->generateTourSlug($user->id,$open_trip->title);
            $openTrip->min_people = $open_trip->min_people;
            $openTrip->max_people = $open_trip->max_people;
            $openTrip->stock= $open_trip->stock;
            $openTrip->price = $open_trip->price;
            $openTrip->price_weekend = $open_trip->price_weekend;
            $openTrip->price_holiday = $open_trip->price_holiday;
            $openTrip->discount = $open_trip->discount;
            $openTrip->discount_weekend = $open_trip->discount_weekend;
            $openTrip->discount_holiday = $open_trip->discount_holiday;
            $openTrip->itinerary = $open_trip->itinerary;
            $openTrip->is_private = $open_trip->is_private;
            $openTrip->status = $open_trip->status;
            $openTrip->image_id = isset($result->open_trip_image) ? $result->open_trip_image->id : $parent->image_id;
            $openTrip->banner_image_id = $openTrip->image_id;
            $openTrip->create_user = $parent->create_user;
            $openTrip->parent_id = $parent->id;
            $openTrip->save();

            $data->open_trip = $openTrip;
        }

        $tempPTrip = [];
        for ($i=0; $i < $private_trip_count; $i++) { 
            $pt = $private_trip[$i];
            $privateTrip = new Tour();
            $privateTrip->title = $pt->title;
            $privateTrip->slug = $this->generateTourSlug($user->id,$pt->title);
            $privateTrip->min_people = $pt->min_people;
            $privateTrip->max_people = $pt->max_people;
            $privateTrip->stock= $pt->stock;
            $privateTrip->price = $pt->price;
            $privateTrip->price_weekend = $pt->price_weekend;
            $privateTrip->price_holiday = $pt->price_holiday;
            $privateTrip->discount = $pt->discount;
            $privateTrip->discount_weekend = $pt->discount_weekend;
            $privateTrip->discount_holiday = $pt->discount_holiday;
            $privateTrip->itinerary = $pt->itinerary;
            $privateTrip->is_private = $pt->is_private;
            $privateTrip->status = $pt->status;
            $privateTrip->image_id = isset($result->{"private_trip_image_".($i+1)}) ? $result->{"private_trip_image_".($i+1)}->id : $parent->image_id;
            $privateTrip->banner_image_id = $privateTrip->image_id;
            $privateTrip->create_user = $parent->create_user;
            $privateTrip->parent_id = $parent->id;
            $privateTrip->save();
            $tempPTrip[] = $privateTrip;
        }

        $data->private_trip = $tempPTrip;
        
        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $data
        ]);
    }

    public function update($id, Request $request){
        $data = '';
        $parent = TourParent::find($id);
        if($parent){
            $parent->title = $request->title;
            $parent->content = $request->content;
            $parent->status = $request->status;
            $parent->save();
            $data = $parent;
            $banner = $parent->image_id;
            $gallery = explode(',',$parent->gallery);

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
                $response = $post->post($cdn, ["prefix" => $parent->slug]);
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
            $parent->image_id = $banner;
            $parent->gallery = implode(',',$gallery);
            $parent->updated_at = date('Y-m-d H:i:s');
            $parent->save();

            $open_trip = json_decode($request->open_trip);

            if($open_trip->is_active){
                $openTrip = Tour::find($open_trip->id);
                if($openTrip){
                    $banner = $openTrip->image_id;
                    $openTrip->title = $open_trip->title;
                    $openTrip->min_people = $open_trip->min_people;
                    $openTrip->max_people = $open_trip->max_people;
                    $openTrip->stock= $open_trip->stock;
                    $openTrip->price = $open_trip->price;
                    $openTrip->price_weekend = $open_trip->price_weekend;
                    $openTrip->price_holiday = $open_trip->price_holiday;
                    $openTrip->discount = $open_trip->discount;
                    $openTrip->discount_weekend = $open_trip->discount_weekend;
                    $openTrip->discount_holiday = $open_trip->discount_holiday;
                    $openTrip->itinerary = $open_trip->itinerary;
                    $openTrip->is_private = $open_trip->is_private;
                    $openTrip->status = $open_trip->status;
                    $openTrip->image_id = isset($result->open_trip_image) ? $result->open_trip_image->id : $banner;
                    $openTrip->banner_image_id = $openTrip->image_id;
                    $openTrip->create_user = $parent->create_user;
                    $openTrip->parent_id = $parent->id;
                    $openTrip->updated_at = date('Y-m-d H:i:s');
                    $openTrip->save();
                    $data->open_trip = $openTrip;
                }
            }

            $private_trip = json_decode($request->private_trip) ?? null;
            $private_trip_count = 0;
            if($private_trip){
                $private_trip_count = sizeof($private_trip);
            }
            $tempPTrip = [];
            for ($i=0; $i < $private_trip_count; $i++) { 
                $pt = $private_trip[$i];
                $privateTrip = Tour::find($pt->id);
                if($privateTrip){
                    $banner = $privateTrip->image_id;
                    $privateTrip->title = $pt->title;
                    $privateTrip->min_people = $pt->min_people;
                    $privateTrip->max_people = $pt->max_people;
                    $privateTrip->stock= $pt->stock;
                    $privateTrip->price = $pt->price;
                    $privateTrip->price_weekend = $pt->price_weekend;
                    $privateTrip->price_holiday = $pt->price_holiday;
                    $privateTrip->discount = $pt->discount;
                    $privateTrip->discount_weekend = $pt->discount_weekend;
                    $privateTrip->discount_holiday = $pt->discount_holiday;
                    $privateTrip->itinerary = $pt->itinerary;
                    $privateTrip->is_private = $pt->is_private;
                    $privateTrip->status = $pt->status;
                    $privateTrip->image_id = isset($result->{"private_trip_image_".($i+1)}) ? $result->{"private_trip_image_".($i+1)}->id : $banner;
                    $privateTrip->banner_image_id = $privateTrip->image_id;
                    $privateTrip->create_user = $parent->create_user;
                    $privateTrip->parent_id = $parent->id;
                    $privateTrip->updated_at = date('Y-m-d H:i:s');
                    $privateTrip->save();
                    $tempPTrip[] = $privateTrip;
                }
            }

            $data->private_trip = $tempPTrip;
            
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

    public function delete($id){
        $parent = TourParent::find($id);
        if($parent){
            $tours = Tour::where('parent_id',$id)->get();
            foreach($tours as &$k){
                $k->delete();
            }
            $parent->delete();
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
