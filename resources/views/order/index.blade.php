@extends('layouts.layout')

@section('title', 'Orders')

@section('content')
    <div class="row">
        <div class="col-8">
            <h1>Orders List</h1>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">Order ID</th>
                        <th scope="col">Customer Name</th>
                        <th scope="col">Total Weight</th>
                        <th scope="col">Robot Name</th>
                        <th scope="col">Most Used Category</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td scope="row">{{ $order->id }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>{{ number_format($order->total_weight, 2) }}</td>
                            <td>{{ $order->robot_name }}</td>
                            <td>{{ $order->most_used_category }}</td>
                            <td>
                                <a href="{{ url('/order', $order->id) }}" class="btn btn-secondary">View/Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="col-4">

        </div>
    </div>
@endsection
