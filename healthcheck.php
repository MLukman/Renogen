<?php
print json_encode(array(
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'hostname' => gethostname(),
));
