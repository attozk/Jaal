ab.debug(true, true);
session = ab.connect(
    // The WebSocket URI of the WAMP server
    'ws://mine.pk:900',

    // The onconnect handler
    function (session) {
        // WAMP session established here ..
    },

    // The onhangup handler
    function (code, reason, detail) {
        // WAMP session closed here ..
    },

    // The session options
    {
        'maxRetries': 60,
        'retryDelay': 2000
    }
);