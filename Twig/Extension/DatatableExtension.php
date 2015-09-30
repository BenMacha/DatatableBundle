<?php

namespace Waldo\DatatableBundle\Twig\Extension;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Waldo\DatatableBundle\Util\Datatable;
use Waldo\DatatableBundle\Util\ArrayMerge;

class DatatableExtension extends \Twig_Extension
{

    use ArrayMerge;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param FormFactoryInterface$formFactory
     * @param DataCollectorTranslator $translator
     */
    public function __construct(FormFactoryInterface $formFactory, TranslatorInterface $translator)
    {
        $this->formFactory = $formFactory;
        $this->translator = $translator;
    }

        /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('datatable', array($this, 'datatable'),
                    array("is_safe" => array("html"), 'needs_environment' => true)),
            new \Twig_SimpleFunction('datatable_html', array($this, 'datatableHtml'),
                    array("is_safe" => array("html"), 'needs_environment' => true)),
            new \Twig_SimpleFunction('datatable_js', array($this, 'datatableJs'),
                    array("is_safe" => array("html"), 'needs_environment' => true))
        );
    }

    /**
     * Converts a string to time
     *
     * @param string $string
     * @return int
     */
    public function datatable(\Twig_Environment $twig, $options)
    {
        $options = $this->buildDatatableTemplate($options);

        $mainTemplate = array_key_exists('main_template', $options) ? $options['main_template'] : 'WaldoDatatableBundle:Main:index.html.twig';

        return $twig->render($mainTemplate, $options);
    }

    /**
     * Converts a string to time
     *
     * @param string $string
     * @return int
     */
    public function datatableJs(\Twig_Environment $twig, $options)
    {
        $options = $this->buildDatatableTemplate($options, "js");

        $mainTemplate = array_key_exists('main_template', $options) ? $options['js_template'] : 'WaldoDatatableBundle:Main:datatableJs.html.twig';

        return $twig->render($mainTemplate, $options);
    }

    /**
     * Converts a string to time
     *
     * @param string $string
     * @return int
     */
    public function datatableHtml(\Twig_Environment $twig, $options)
    {
        if (!isset($options['id'])) {
            $options['id'] = 'ali-dta_' . md5(mt_rand(1, 100));
        }
        $dt = Datatable::getInstance($options['id']);

        $options['fields'] = $dt->getFields();
        $options['search'] = $dt->getSearch();
        $options['searchFields'] = $dt->getSearchFields();
        $options['multiple'] = $dt->getMultiple();

        $mainTemplate = 'WaldoDatatableBundle:Main:datatableHtml.html.twig';

        if (isset($options['html_template'])) {
            $mainTemplate = $options['html_template'];
        }

        return $twig->render($mainTemplate, $options);
    }

    private function buildDatatableTemplate($options, $type = null)
    {
        if (!isset($options['id'])) {
            $options['id'] = 'ali-dta_' . md5(mt_rand(1, 100));
        }

        $dt = Datatable::getInstance($options['id']);

        $config = $dt->getConfiguration();

        $options['js'] = array_merge($options['js'], $config['js']);
        $options['fields'] = $dt->getFields();
        $options['delete_form'] = $this->createDeleteForm('_id_')->createView();
        $options['search'] = $dt->getSearch();
        $options['global_search'] = $dt->getGlobalSearch();
        $options['multiple'] = $dt->getMultiple();
        $options['searchFields'] = $dt->getSearchFields();
        $options['sort'] = $dt->getOrderField() === null ? null : array(
            array_search($dt->getOrderField(), array_values($dt->getFields())),
            $dt->getOrderType()
        );

        if ($type == "js") {
            $this->buildJs($options, $dt);
        }

        return $options;
    }

    private function buildJs(&$options, $dt)
    {
        if(array_key_exists("ajax", $options['js']) && !is_array($options['js']['ajax'])) {
            $options['js']['ajax'] = array(
                "url" => $options['js']['ajax'],
                "type" => "POST"
            );
        }

        if (count($dt->getHiddenFields()) > 0) {
            $options['js']['columnDefs'][] = array(
                "visible" => "false",
                "targets" => $dt->getHiddenFields()
            );
        }
        if (count($dt->getNotSortableFields()) > 0) {
            $options['js']['columnDefs'] = array(
                "orderable" => "false",
                "targets" => $dt->getNotSortableFields()
            );
        }
        if (count($dt->getNotFilterableFields()) > 0) {
            $options['js']['columnDefs'] = array(
                "searchable" => "false",
                "targets" => $dt->getNotFilterableFields()
            );
        }

        $this->buildTranslation($options);
    }

    private function buildTranslation(&$options)
    {
        if(!array_key_exists("language", $options['js'])) {
            $options['js'] = array('language' => array());
        }

        $baseLanguage = array(
                "processing" =>     $this->translator->trans("datatable.datatable.processing"),
                "search"=>          $this->translator->trans("datatable.datatable.search"),
                "lengthMenu"=>      $this->translator->trans("datatable.datatable.lengthMenu"),
                "info"=>            $this->translator->trans("datatable.datatable.info"),
                "infoEmpty"=>       $this->translator->trans("datatable.datatable.infoEmpty"),
                "infoFiltered"=>    $this->translator->trans("datatable.datatable.infoFiltered"),
                "infoPostFix"=>     $this->translator->trans("datatable.datatable.infoPostFix"),
                "loadingRecords"=>  $this->translator->trans("datatable.datatable.loadingRecords"),
                "zeroRecords"=>     $this->translator->trans("datatable.datatable.zeroRecords"),
                "emptyTable"=>      $this->translator->trans("datatable.datatable.emptyTable"),
                "searchPlaceholder" => $this->translator->trans("datatable.datatable.searchPlaceholder"),
                "paginate"=> array (
                    "first"=>       $this->translator->trans("datatable.datatable.paginate.first"),
                    "previous"=>    $this->translator->trans("datatable.datatable.paginate.previous"),
                    "next"=>        $this->translator->trans("datatable.datatable.paginate.next"),
                    "last"=>        $this->translator->trans("datatable.datatable.paginate.last")
                ),
                "aria"=> array(
                    "sortAscending"=>  $this->translator->trans("datatable.datatable.aria.sortAscending"),
                    "sortDescending"=> $this->translator->trans("datatable.datatable.aria.sortDescending")
                ));

        $options['js']['language'] = $this->arrayMergeRecursiveDistinct($baseLanguage, $options['js']['language']);
    }

    /**
     * create delete form
     *
     * @param type $id
     * @return type
     */
    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
                        ->add('id', 'hidden')
                        ->getForm();
    }

    /**
     * create form builder
     *
     * @param type $data
     * @param array $options
     * @return type
     */
    public function createFormBuilder($data = null, array $options = array())
    {
        return $this->formFactory->createBuilder('form', $data, $options);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'DatatableBundle';
    }

}
