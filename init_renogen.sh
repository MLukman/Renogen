#!/bin/bash

if [ -n "$BASE_URL" ] && [ ! -d "$BASE_URL" ]; then 
  ln -s . "$BASE_URL"
fi

php tools/updateSchemas.php > /dev/null
