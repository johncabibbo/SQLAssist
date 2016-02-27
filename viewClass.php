<?php

class viewClass {

public function getView($pagename='', $data=array()){
	include 'view/'.$pagename;	
}

}