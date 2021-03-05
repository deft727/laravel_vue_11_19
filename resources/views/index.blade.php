<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <meta name="csrf-token" content="{{csrf_token()}}"/>
    <link rel="stylesheet" type="text/css" href="{{asset('assets/libs/bootstrap/css/bootstrap.min.css')}}"/>
</head>

<body>
    <div id="wrapper">
        <div class="container">
            <div id="app">
            </div>
        </div>
    </div><!-- #wrapper -->
    <!-- jQuery -->
    <script src="{{asset('js/app.js')}}"></script>
    <script src="{{asset('assets/libs/bootstrap/js/bootstrap.min.js')}}"></script>
    <!-- <script src="https://maps.googleapis.com/maps/api/js?key='{{ env('GOOGLE_API_KEY') }}'&libraries=places"></script> -->
</body>
</html>
