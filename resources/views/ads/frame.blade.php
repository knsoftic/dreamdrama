<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="origin">
    <style>
        html, body { margin: 0; padding: 0; background: transparent; color: #fff; font-family: system-ui, sans-serif; }
        body { min-height: 60px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        #ad-root { max-width: 100%; text-align: center; }
        #ad-root img, #ad-root iframe { max-width: 100%; }
    </style>
</head>
<body>
<div id="ad-root">{!! $unit->code !!}</div>
<script>
    // Tell the parent page how tall the ad is so banner frames fit it.
    setInterval(function () {
        try { parent.postMessage({ adFrameHeight: document.getElementById('ad-root').scrollHeight }, location.origin); } catch (e) {}
    }, 800);
</script>
</body>
</html>
