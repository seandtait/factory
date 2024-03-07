@extends('layouts.layout')

@section('title', 'Orders')

@section('content')
    <div class="row">
        <div class="col-12">
            <p class="mt-3 back-link"><a href="{{ url('/orders') }}"><- Back </a></p>
        </div>
    </div>

    @if (Session::has('success'))
        <div class="row">
            <div class="col-12">

                <div class="alert alert-success">
                    {{ Session::get('success') }}
                </div>

            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        {{ $error }}
                        <br />
                    @endforeach
                </div>

            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-8">
            <h1>{{ $order->robot_name }}</h1>
            <h4>Order {{ $order->id }}</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">Customer Name</th>
                        <th scope="col">Total Weight</th>
                        <th scope="col">Robot Name</th>
                        <th scope="col">Most Used Category</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $order->customer_name }}</td>
                        <td>{{ number_format($order->weight, 3) }}</td>
                        <td>{{ $order->robot_name }}</td>
                        <td>{{ $order->most_used_category }}</td>
                    </tr>
                </tbody>
            </table>

            <br class="m-5">

            <h2>Parts</h2>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">SKU</th>
                        <th scope="col">Description</th>
                        <th scope="col">Category</th>
                        <th scope="col">Weight</th>
                        <th scope="col">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->orderItems as $orderItem)
                        <tr>
                            <td scope="row">{{ $orderItem->sku }}</th>
                            <td>{{ $orderItem->description }}</td>
                            <td>{{ $orderItem->category }}</td>
                            <td>{{ number_format($orderItem->weight, 3) }}</td>
                            <td>{{ $orderItem->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
        <div class="col-4">
            <div class="p-3 m-5">
                <form method="POST" action="/order/update/{{ $order->id }}" class="needs-validation" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="robot_name" class="form-label">Change Robot Name</label>
                        <input type="text" class="form-control" id="robot_name" name="robot_name">
                    </div>
                    <button type="submit" class="btn btn-secondary">Submit</button>
                </form>
            </div>
        </div>
    </div>
@endsection
