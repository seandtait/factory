@extends('layouts.layout')

@section('title', 'Admin')

@section('content')
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
        <div class="col-12">
            <div class="m-5 p-5 text-center">
                <form action="{{ url('upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="csv_file" required>
                    <button type="submit">Upload CSV</button>
                </form>
            </div>
        </div>
    </div>
@endsection
