<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

// Models
use App\Models\Order;
use App\Models\OrderItem;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // View the robots
        $orders = Order::all();

        return view('order.index', ['orders' => $orders]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            abort(404, 'Order not found');
        }

        return view('order.show', ['order' => $order]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $request->validate([
                'robot_name' => 'required', 
            ]);
        
            // Find the model instance you want to update
            $order = Order::find($id);
        
            // Update the model instance with the request data
            $order->update([
                'robot_name' => $request->input('robot_name'),
            ]);
        
            return redirect()->action([OrderController::class, 'show'], ['id' => $order->id])->with('success', 'Name updated successfully!');
        } catch (\Throwable $th) {
            return back()->withInput()->withErrors(['error' => 'Error updating robot name: ' . $th->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
