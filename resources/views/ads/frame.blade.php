<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="origin">
    <base target="_blank">
    <style>
        html, body { margin: 0; padding: 0; background: transparent; color: #fff; font-family: system-ui, sans-serif; }
        body { min-height: 60px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        #ad-root { max-width: 100%; text-align: center; }
        #ad-root img, #ad-root iframe { max-width: 100%; }
        #ad-fallback { color: rgba(255,255,255,.45); font-size: 13px; }
    </style>
</head>
<body>
<div id="ad-root">{!! $unit->code !!}</div>
<div id="ad-fallback" hidden>Advertisement</div>
<script>
    // Report height to the parent so banner frames can fit the ad.
    setInterval(function () {
        try { parent.postMessage({ adFrameHeight: document.getElementById('ad-root').scrollHeight }, location.origin); } catch (e) {}
    }, 800);

    // If the ad code rendered nothing visible after a few seconds (e.g. a
    // pop-under / push code that belongs in global scripts, not here), show a
    // small label so the box is not just blank.
    setTimeout(function () {
        var root = document.getElementById('ad-root');
        if (!root || root.offsetHeight < 10) {
            document.getElementById('ad-fallback').hidden = false;
        }
    }, 3500);
</script>
</body>
</html>
