<?php

function foo(?\SplStack $a) {
	if (is_null($a)) {
		$a=new \SplStack();
	}
	echo $a->count();
}

function foo2(?\SplStack $b) {
	if($b==null) {
		$b = new \SplStack();
	}
	echo $b->count();
}

function foo3(?\SplStack $c) {
	if($c===null) {
		$c=new \SplStack();
	}
	echo $c->count();
}

function baz(?\SplStack $d) {
	if($d===null) {
		return;
	}
	echo $d->count();
}


function baz2(?\SplStack $e) {
	if($e==null) {
		return;
	}
	echo $e->count();
}

function baz3(?\SplStack $f) {
	if(is_null($f)) {
		return;
	} else {
		echo $f->count();
	}
}


function baz4(?\SplStack $g){
	if (!isset($g)) {
		return;
	}
	echo $g->count();
}


function bar(?\SplStack $h) {
	echo ($h ? $h->count() : 0);
}


function bar2(?\SplStack $i) {
	echo (!is_null($i) ? $i->count() : 0);
}


function bar3(?\SplStack $j) {
	echo ($j!=null ? $j->count() : 0);
}



function bar4(?\SplStack $k) {
	echo(!empty($k) ? $k->count() : 0);
}


function bet(?\SplStack $l) {
	echo (is_null($l) ? 0 : $l->count());
}


function bag(?\SplStack $m, ?\SplStack $m2) {
	if ($m!==NULL && $m2!=NULL) {
		echo $m->count()." ".$m2->count();
	}
}

function bag2(null|\SqlQueue|\SplStack $n) {
	if ($n instanceof \SplStack || $n instanceof \SplQueue) {
		echo $n->count();
	}
}


function bag3(?\SplQueue $n) {
	$n = $n ?: new \SplQueue();
	echo $n->count();
}
