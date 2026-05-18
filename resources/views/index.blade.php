<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Studio V2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        @include('crud-service-generator::js.navigation')
        @include('crud-service-generator::js.app')
    </script>

</head>
<body class="h-full text-gray-100 antialiased" x-data="crudStudio()">

    <div class="flex h-screen overflow-hidden">

        <x-app-sidebar />

    </div>

</body>
</html>