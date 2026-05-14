<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\MailController;
use Nette\Utils\Random;

class MarketPlaceController extends Controller
{
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

public function createListing(Request $request)
{
    $d_token = $request->header('Authorization');

    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

    if (env('REV_APP_KEY') == $accessTokennewinfo) {

        $validator = Validator::make($request->all(), [

            'user_id' => 'required',

            'seller_type' => 'required|in:product,artisan,service',

            'category' => 'required|string',

            'title' => 'required|string|max:255',

            'description' => 'required|string',

            'price' => 'required|numeric|min:1',

            'images' => 'nullable|array',

            'location' => 'required|string',

            'delivery_available' => 'required|boolean',

            'allows_instalment' => 'required|boolean',

            'min_instalment_count' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {

            return response()->json([
                "success" => false,
                "message" => $validator->errors()->first()
            ]);
        }

    

        // validation on condition
        //products
        if ($request->seller_type == "product") {

            if (!$request->condition) {

                return response()->json([
                    "success" => false,
                    "message" => "Condition is required for product listings"
                ]);
            }

            if (!in_array($request->condition, ['New', 'Used'])) {

                return response()->json([
                    "success" => false,
                    "message" => "Condition must be New or Used"
                ]);
            }
        }

        // ARTISAN
        if ($request->seller_type == "artisan") {

            if (!$request->lead_time) {

                return response()->json([
                    "success" => false,
                    "message" => "Lead time is required for artisan listings"
                ]);
            }
        }

        // SERVICE
        if ($request->seller_type == "service") {

            if (!$request->availability) {

                return response()->json([
                    "success" => false,
                    "message" => "Availability is required for service listings"
                ]);
            }
        }

     //validation via categories
        $categories = [

            "product" => [
                "Electronics & Gadgets",
                "Fashion & Clothing",
                "Food & Groceries",
                "Home & Furniture",
                "Beauty & Personal Care",
                "Agricultural Produce",
                "Books & Stationery",
                "Baby & Kids",
                "Auto Parts",
                "Industrial & Equipment"
            ],

            "artisan" => [
                "Fashion & Tailoring",
                "Footwear",
                "Furniture & Woodwork",
                "Art & Paintings",
                "Jewellery & Beadwork",
                "Leather Goods",
                "Ceramics & Pottery",
                "Candles & Soaps",
                "Baskets & Weaving"
            ],

            "service" => [
                "Mechanics & Auto Repair",
                "Electrical",
                "Plumbing",
                "Graphic Design",
                "Photography",
                "Makeup & Beauty",
                "Event Planning",
                "Catering",
                "Cleaning",
                "Tutoring",
                "Hair Styling",
                "Interior Decoration"
            ]
        ];

        if (!in_array(
            $request->category,
            $categories[$request->seller_type]
        )) {

            return response()->json([
                "success" => false,
                "message" => "Invalid category for seller type"
            ]);
        }

  // processing images

        $savedImages = [];

        if ($request->images && is_array($request->images)) {

            foreach ($request->images as $index => $base64Image) {

                try {

                    $image = str_replace(
                        'data:image/png;base64,',
                        '',
                        $base64Image
                    );

                    $image = str_replace(
                        ' ',
                        '+',
                        $image
                    );

                    $imageName = time() . '_' . $index . '.png';

                    \Storage::disk('public')->put(
                        'listing_images/' . $imageName,
                        base64_decode($image)
                    );

                    $savedImages[] =
                        asset('storage/listing_images/' . $imageName);

                } catch (\Exception $e) {

                    return response()->json([
                        "success" => false,
                        "message" => "Invalid image format"
                    ], 400);
                }
            }
        }

// listing

        $listingId = 'lst_' . uniqid();

        DB::table('listings')->insert([

            'listing_id' => $listingId,

            'user_id' => $request->user_id,

            'seller_type' => $request->seller_type,

            'category' => $request->category,

            'title' => $request->title,

            'description' => $request->description,

            'price' => $request->price,

            'images' => json_encode($savedImages),

            'location' => $request->location,

            'condition' => $request->condition,

            'delivery_available' => $request->delivery_available,

            'lead_time' => $request->lead_time,

            'availability' => $request->availability,

            'allows_instalment' => $request->allows_instalment,

            'min_instalment_count' => $request->min_instalment_count,

            'status' => 'active',

            'created_at' => now()
        ]);

  

        return response()->json([

            "success" => true,

            "data" => [

                "listing_id" => $listingId,

                "status" => "active",

                "created_at" => now()
            ]

        ], 201);

    } else {

        return response()->json([

            'success' => false,

            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Token is invalid'
            ]

        ], 401);
    }
}



public function browseListings(Request $request)
{
  
    $d_token = $request->header('Authorization');

    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

    if (env('REV_APP_KEY') == $accessTokennewinfo) {


        $category = $request->query('category');

        $sellerType = $request->query('seller_type');

        $location = $request->query('location');

        $minPrice = $request->query('min_price');

        $maxPrice = $request->query('max_price');

        $allowsInstalment = $request->query('allows_instalment');

        $search = $request->query('search');

        $sort = $request->query('sort', 'newest');

        $page = $request->query('page', 1);

        $limit = $request->query('limit', 12);



        $query = DB::table('listings');

     
        // CATEGORY
        if ($category) {

            $query->where('category', $category);
        }

        // SELLER TYPE
        if ($sellerType) {

            $query->where('seller_type', $sellerType);
        }

        // LOCATION
        if ($location) {

            $query->where('location', 'LIKE', '%' . $location . '%');
        }

        // MIN PRICE
        if ($minPrice) {

            $query->where('price', '>=', $minPrice);
        }

        // MAX PRICE
        if ($maxPrice) {

            $query->where('price', '<=', $maxPrice);
        }

        // INSTALLMENT
        if ($allowsInstalment !== null) {

            $query->where(
                'allows_instalment',
                filter_var($allowsInstalment, FILTER_VALIDATE_BOOLEAN)
            );
        }

        // SEARCH
        if ($search) {

            $query->where(function ($q) use ($search) {

                $q->where('title', 'LIKE', '%' . $search . '%')

                  ->orWhere('description', 'LIKE', '%' . $search . '%')

                  ->orWhere('category', 'LIKE', '%' . $search . '%');
            });
        }

   //sorting

        switch ($sort) {

            case 'oldest':

                $query->orderBy('created_at', 'asc');

                break;

            case 'price_low':

                $query->orderBy('price', 'asc');

                break;

            case 'price_high':

                $query->orderBy('price', 'desc');

                break;

            default:

                $query->orderBy('created_at', 'desc');

                break;
        }

        
        $total = $query->count();

        $listings = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();


        $result = [];

        foreach ($listings as $listing) {

 //seller info
            $seller = DB::table('users')
                ->where('user_id', $listing->user_id)
                ->first();

            $tier = "Bronze";

            if (($seller->ajo_score ?? 0) >= 91) {

                $tier = "Elite";

            } elseif (($seller->ajo_score ?? 0) >= 76) {

                $tier = "Gold";

            } elseif (($seller->ajo_score ?? 0) >= 61) {

                $tier = "Silver";
            }

// get image

            $images = json_decode($listing->images, true) ?? [];

            $thumbnail = count($images) > 0
                ? $images[0]
                : null;

// compliation of data collected

            $result[] = [

                "listing_id" => $listing->listing_id,

                "seller_type" => $listing->seller_type,

                "category" => $listing->category,

                "title" => $listing->title,

                "price" => $listing->price,

                "allows_instalment" => (bool) $listing->allows_instalment,

                "min_instalment_count" => $listing->min_instalment_count,

                "thumbnail" => $thumbnail,

                "location" => $listing->location,

                "seller" => [

                    "name" => $seller->full_name ?? null,

                    "ajo_score" => $seller->ajo_score ?? 0,

                    "score_tier" => $tier,

                    "completed_escrows" =>
                        $seller->completed_escrows ?? 0
                ],

                "created_at" => $listing->created_at
            ];
        }

// response

        return response()->json([

            "success" => true,

            "data" => [

                "listings" => $result,

                "total" => $total,

                "page" => (int) $page,

                "limit" => (int) $limit
            ]
        ]);

    } else {

        return response()->json([

            "success" => false,

            "error" => [

                "code" => "UNAUTHORIZED",

                "message" => "Token is invalid"
            ]

        ]);
    }
}


public function getListingDetail(Request $request, $listingId)
{


    $d_token = $request->header('Authorization');

    $accessTokennewinfo = trim(str_replace("Bearer", "", $d_token));

    if (env('REV_APP_KEY') == $accessTokennewinfo) {

        $listing = DB::table('listings')
            ->where('listing_id', $listingId)
            ->first();

        if (!$listing) {

            return response()->json([
                "success" => false,
                "message" => "Listing not found"
            ], 404);
        }


        $seller = DB::table('users')
            ->where('user_id', $listing->user_id)
            ->first();

        $score = $seller->ajo_score ?? 0;

        $tier = "Bronze";

        if ($score >= 91) {
            $tier = "Elite";
        } elseif ($score >= 76) {
            $tier = "Gold";
        } elseif ($score >= 61) {
            $tier = "Silver";
        }

  

        $images = json_decode($listing->images, true) ?? [];

    

        return response()->json([
            "success" => true,
            "data" => [
                "listing_id" => $listing->listing_id,
                "seller_type" => $listing->seller_type,
                "category" => $listing->category,
                "title" => $listing->title,
                "description" => $listing->description,
                "price" => $listing->price,
                "images" => $images,
                "location" => $listing->location,
                "delivery_available" => (bool) $listing->delivery_available,
                "lead_time" => $listing->lead_time,
                "allows_instalment" => (bool) $listing->allows_instalment,
                "min_instalment_count" => $listing->min_instalment_count,
                "status" => $listing->status,
                "created_at" => $listing->created_at,

                "seller" => [
                    "user_id" => $seller->user_id ?? null,
                    "name" => $seller->full_name ?? null,
                    "photo" => $seller->profile_photo ?? null,
                    "ajo_score" => $score,
                    "score_tier" => $tier,
                    "member_since" => $seller->Date ?? null,
                    "completed_escrows" => $seller->completed_escrows ?? 0,
                    "dispute_rate" => $seller->dispute_rate ?? "0%",
                    "response_rate" => $seller->response_rate ?? "0%"
                ]
            ]
        ]);

    } else {

        return response()->json([
            "success" => false,
            "error" => [
                "code" => "UNAUTHORIZED",
                "message" => "Token is invalid"
            ]
        ]);
    }
}
//stop
}
