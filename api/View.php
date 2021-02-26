<?php
//SIMPLE TEMPLATE
//usage e.g.
// $view = new View();
// $view->data['foo'] = 'The title';
// $view->data['bar'] = 'The body';
// $view->render(dirname(__DIR__) . '/api/somefile.template.php');

class View
{
    /**
     * Set data from controller: $view->data['variable'] = 'value';
     * @var array
     */
    public $data = [];

    /**
     * @var sting Path to template file.
     */ 
    function render($template)
    {
        if (!is_file($template)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        // define a closure with a scope for the variable extraction
        $result = function($file, array $data = array()) {
            ob_start();
            extract($data, EXTR_SKIP);
            try {
                include $file;
            } catch (\Exception $e) {
                ob_end_clean();
                throw $e;
            }
            return ob_get_clean();
        };

        // call the closure
        echo $result($template, $this->data);
    }
}
?>