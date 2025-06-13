<?php

namespace App;

class DS extends \Prefab
{
    //debug
    function v($data){
        echo '<script>console.log('.json_encode($data).')</script>';
    }

}