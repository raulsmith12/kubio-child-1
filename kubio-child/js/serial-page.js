document.addEventListener('DOMContentLoaded', function () {
    var mapsBtn = document.getElementById('open-maps-btn');
    if (mapsBtn) {
        mapsBtn.addEventListener('click', function (e) {
            e.preventDefault();

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const query = 'police station';
                    const mapsUrl = 'https://www.google.com/maps/search/' + encodeURIComponent(query) + '/@' + lat + ',' + lon + ',15z';
                    window.open(mapsUrl, '_blank');
                }, function (error) {
                    alert('Location access denied or unavailable.');
                });
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        });
    }
});
