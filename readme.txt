sprite-engine

spenibus.net
https://github.com/spenibus/sprite-engine-php
https://gitlab.com/spenibus/sprite-engine-php

A simple sprite generator for the web.
Put images in "./img" then add the script as a css to your html:
<link href="sprite-engine/index.php?css=16,24,32" type="text/css" rel="stylesheet"/>

Then use the available classes on an element (ex: ./img/youtube.png)
<span class="se_32_youtube"></span>
This will display a 32x32 icon based on ./img/youtube.png

arguments
   css   comma separated list of sizes for the sprites