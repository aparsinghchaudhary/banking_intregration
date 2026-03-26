<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Comment;
use App\Models\Replay;

class HomeController extends Controller
{

    public function index()
    {
        $product = Product::paginate(10);
        $comment = Comment::orderby('id', 'desc')->get();
        $reply = Replay::all();

        return view('home.userpage', compact('product', 'comment', 'reply'));
    }

    public function redirect()
    {
        $usertype = Auth::user()->usertype;

        if ($usertype == '1') {

            $total_product = Product::count();
            $total_order = Order::count();
            $total_user = User::count();

            $orders = Order::all();
            $total_revenue = 0;

            foreach ($orders as $order) {
                $total_revenue += $order->price;
            }

            $total_delivered = Order::where('delivery_status', 'delivered')->count();
            $total_processing = Order::where('delivery_status', 'processing')->count();

            return view('admin.home', compact(
                'total_product',
                'total_order',
                'total_user',
                'total_revenue',
                'total_delivered',
                'total_processing'
            ));
        } else {

            $product = Product::paginate(10);
            $comment = Comment::orderby('id', 'desc')->get();
            $reply = Replay::all();

            return view('home.userpage', compact('product', 'comment', 'reply'));
        }
    }

    public function product_details($id)
    {
        $product = Product::find($id);
        return view('home.product_details', compact('product'));
    }

    public function add_cart(Request $request, $id)
    {
        if (Auth::id()) {

            $user = Auth::user();
            $product = Product::find($id);

            $userid = $user->id;

            $product_exist_id = Cart::where('product_id', $id)
                ->where('user_id', $userid)
                ->value('id');

            if ($product_exist_id) {

                $cart = Cart::find($product_exist_id);

                $cart->quantity += $request->quantity;

                if ($product->discount_price != null) {
                    $cart->price = $product->discount_price * $cart->quantity;
                } else {
                    $cart->price = $product->price * $cart->quantity;
                }

                $cart->save();

            } else {

                $cart = new Cart;

                $cart->name = $user->name;
                $cart->email = $user->email;
                $cart->phone = $user->phone;
                $cart->address = $user->address;
                $cart->user_id = $user->id;
                $cart->product_title = $product->title;

                if ($product->discount_price != null) {
                    $cart->price = $product->discount_price * $request->quantity;
                } else {
                    $cart->price = $product->price * $request->quantity;
                }

                $cart->image = $product->image;
                $cart->product_id = $product->id;
                $cart->quantity = $request->quantity;

                $cart->save();
            }

            return redirect()->back()->with('message', 'Product added successfully');

        } else {
            return redirect('login');
        }
    }

    public function show_cart()
    {
        if (Auth::id()) {

            $id = Auth::user()->id;
            $cart = Cart::where('user_id', $id)->get();

            $totalprice = 0;
            foreach ($cart as $item) {
                $totalprice += $item->price;
            }

            // eSewa setup
            $transaction_uuid = uniqid();
            $product_code = "EPAYTEST";
            $secret = "8gBm/:&EnhH.1/q";

            $data = "total_amount={$totalprice},transaction_uuid={$transaction_uuid},product_code={$product_code}";
            $signature = base64_encode(hash_hmac('sha256', $data, $secret, true));

            return view('home.showcart', compact(
                'cart',
                'totalprice',
                'transaction_uuid',
                'signature'
            ));
        } else {
            return redirect('login');
        }
    }

    public function remove_cart($id)
    {
        Cart::find($id)->delete();
        return redirect()->back();
    }

    public function cash_order()
    {
        $user = Auth::user();
        $userid = $user->id;

        $cartItems = Cart::where('user_id', $userid)->get();

        foreach ($cartItems as $item) {

            $order = new Order;

            $order->name = $item->name;
            $order->email = $item->email;
            $order->phone = $item->phone;
            $order->address = $item->address;
            $order->user_id = $item->user_id;
            $order->product_title = $item->product_title;

            $order->price = $item->price;
            $order->quantity = $item->quantity;
            $order->image = $item->image;
            $order->product_id = $item->product_id;

            $order->payment_status = 'cash on delivery';
            $order->delivery_status = 'processing';

            $order->save();

            $item->delete();
        }

        return redirect()->back()->with('message', 'Order placed successfully');
    }

    public function show_order()
    {
        if (Auth::id()) {

            $userid = Auth::user()->id;
            $order = Order::where('user_id', $userid)->get();

            return view('home.order', compact('order'));
        } else {
            return redirect('login');
        }
    }

    public function cancle_order($id)
    {
        $order = Order::find($id);
        $order->delivery_status = 'Order Cancelled';
        $order->save();

        return redirect()->back();
    }

    public function add_comment(Request $req)
    {
        if (Auth::id()) {

            $comment = new Comment;
            $comment->name = Auth::user()->name;
            $comment->user_id = Auth::user()->id;
            $comment->comment = $req->comment;
            $comment->save();

            return redirect()->back();
        } else {
            return redirect('login');
        }
    }

    public function add_reply(Request $request)
    {
        if (Auth::id()) {

            $reply = new Replay;
            $reply->name = Auth::user()->name;
            $reply->user_id = Auth::user()->id;
            $reply->comment_id = $request->commentId;
            $reply->replay = $request->reply;
            $reply->save();

            return redirect()->back();
        } else {
            return redirect('login');
        }
    }

    public function product_search(Request $req)
    {
        $search_text = $req->search;

        $product = Product::where('title', 'LIKE', "%$search_text%")
            ->orWhere('catagory', 'LIKE', "%$search_text%")
            ->paginate(10);

        $comment = Comment::orderby('id', 'desc')->get();
        $reply = Replay::all();

        return view('home.userpage', compact('product', 'comment', 'reply'));
    }

    public function products()
    {
        $product = Product::paginate(10);
        $comment = Comment::orderby('id', 'desc')->get();
        $reply = Replay::all();

        return view('home.all_product', compact('product', 'comment', 'reply'));
    }

    public function search_product(Request $req)
    {
        $search_text = $req->search;

        $product = Product::where('title', 'LIKE', "%$search_text%")
            ->orWhere('catagory', 'LIKE', "%$search_text%")
            ->paginate(10);

        $comment = Comment::orderby('id', 'desc')->get();
        $reply = Replay::all();

        return view('home.all_product', compact('product', 'comment', 'reply'));
    }

    public function contact_page()
    {
        return view('home.contact');
    }

    // ✅ NEW eSewa VERIFY
    public function verify(Request $request)
{
    if (!$request->has('data')) {
        return redirect()->route('payment.failure')
            ->with('message', 'Invalid payment response');
    }

    $decoded = base64_decode($request->data);
    $response = json_decode($decoded, true);

    if ($response['status'] == 'COMPLETE') {

        $transaction_uuid = $response['transaction_uuid'];
        $total_amount = $response['total_amount'];

        // Verify with eSewa server
        $url = "https://rc.esewa.com.np/api/epay/transaction/status/?product_code=EPAYTEST&total_amount={$total_amount}&transaction_uuid={$transaction_uuid}";

        $result = file_get_contents($url);
        $result = json_decode($result, true);

        if ($result['status'] != 'COMPLETE') {
            return redirect()->route('payment.failure')
                ->with('message', 'Payment verification failed');
        }

        // Prevent duplicate orders
        if (Order::where('transaction_uuid', $transaction_uuid)->exists()) {
            return redirect()->route('payment.success')
                ->with('message', 'Order already processed');
        }

        $user = Auth::user();
        $cartItems = Cart::where('user_id', $user->id)->get();

        foreach ($cartItems as $item) {

            $order = new Order;

            $order->name = $item->name;
            $order->email = $item->email;
            $order->phone = $item->phone;
            $order->address = $item->address;
            $order->user_id = $item->user_id;
            $order->product_title = $item->product_title;

            $order->price = $item->price;
            $order->quantity = $item->quantity;
            $order->image = $item->image;
            $order->product_id = $item->product_id;

            $order->transaction_uuid = $transaction_uuid;

            $order->payment_status = 'paid with esewa';
            $order->delivery_status = 'processing';

            $order->save();

            $item->delete();
        }

        return redirect()->route('payment.success')
            ->with('message', 'Payment Successful! Order placed.');
    }

    return redirect()->route('payment.failure')
        ->with('message', 'Payment Failed');
}
}