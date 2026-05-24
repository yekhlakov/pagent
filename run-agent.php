<?php

require ('vendor/autoload.php');


$agent = new Yekhlakov\PAgent\Agent(llm: "local");


$agent->handle("
1. Explore php code in local `src/Agent.php` file.
2. Produce a description of this program: its purpose, principles of operation, required configuration variables etc.
3. Write the description you produced to local file `README.md` (overwriting its old contents)
");

