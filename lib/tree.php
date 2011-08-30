<?php

function display_tree($root) {
	// retrieve the left and right value of the $root node
	$sql = 'SELECT lft, rgt
		FROM tree
		WHERE title = ' . $root;
	$result = mysql_query($sql);
	$row = mysql_fetch_array($result);
	
	// start with an empty $right stack
	$right = array();
	
	// now, retrieve all descendants of the $root node
	$result = mysql_query('SELECT title, lft, rgt FROM tree ' . 
		'WHERE lft BETWEEN '.$row['lft'].' AND ' . 
		$row['rgt'].' ORDER BY lft ASC;');
	
	// display each row
	while ($row = mysql_fetch_array($result)) {
		// only check stack if there is one
		if (count($right)>0) {
			// check if we should remove a node from the stack
			while ($right[count($right)-1]<$row['rgt']) {
				array_pop($right);
			}
		}
		
		// display indented node title
		echo str_repeat('  ',count($right)).$row['title']."\n";
		
		// add this node to the stack
		$right[] = $row['rgt'];
	}  
}

// How may descendants
//descendants = (right â€“ left - 1) / 2

//
// SELECT title FROM tree WHERE lft < 4 AND rgt > 5 ORDER BY lft ASC;

// Convert simple nodes to new tree 
function rebuild_tree($parent, $left) {
	// the right value of this node is the left value + 1
	$right = $left+1;
	
	// get all children of this node
	$result = mysql_query('SELECT title FROM tree ' . 
		'WHERE parent="'.$parent.'";');
	while ($row = mysql_fetch_array($result)) {
		// recursive execution of this function for each
		// child of this node
		// $right is the current right value, which is
		// incremented by the rebuild_tree function
		$right = rebuild_tree($row['title'], $right);
	}
	
	// we've got the left value, and now that we've processed
	// the children of this node we also know the right value
	mysql_query('UPDATE tree SET lft='.$left.', rgt=' . 
		$right.' WHERE title="'.$parent.'";');
	
	// return the right value of this node + 1
	return $right+1;
}

?>