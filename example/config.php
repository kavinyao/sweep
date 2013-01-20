<?php
$module = array(
    'title' => 'Sweep, the PHP template generator',
    'description' => '<p>The <span>quick</span> brown fox jumps over the lazy dog.</p>',
    'html' => '<div><h3>Title</h3><p>This is very <strong>important</strong> information! <em>Keep it in mind!</em></p><div>',
);

class Person {
    function __construct($given, $family) {
        $this->name = compact('given', 'family');
    }
}

$module['items'] = array(
    new Person('Brad', 'Pitt'),
    new Person('Shawn', 'Brown'),
    new Person('Stephen', 'King'),
);

$module['guests'] = array('Orwell', 'Huxley', 'Butler', 'Atwood');

$module['has_more'] = false;
