<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StellarQuery - DB Manager</title>
    <link rel="stylesheet" href="public/css/uikit.min.css" />
    <link rel="stylesheet" href="public/css/style.css" />
</head>
<body style="display: flex; flex-direction: column; min-height: 100vh;">
    <div id="global-loader" class="hide-loader">
        <div uk-spinner="ratio: 1.2"></div>
        <h6 id="loader-msg" style="margin-top: 10px; color: #475569; font-weight: 600;">Processing...</h6>
    </div>