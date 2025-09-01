<?
namespace App;

use App\Utils\Assets;   
use App\F4;
use App\Component\ComponentManager;
/**
 * 
 */
final class App
{
	private F4 $f3; 
	private Assets $assets;
	private $meta; 
	public ComponentManager $component_manager; 
	private $layout = 'layout.php';
	const BUFFER = ['head' => '{{__headBufferf3}}', 'footer' => '{{__footerBufferf3}}'];
	
	function __construct(F4 $f3, Assets $assets, ComponentManager $component_manager)
	{
		$this->f3 = $f3;
		$this->assets = $assets;
		$this->component_manager = $component_manager;
		$this->setDefault();
	}

	public function setMeta(array $meta){ 	
		$this->meta = array_merge($this->meta,$meta);
	}

	public function getMeta($key, $def = ''){
		return !empty($this->meta[$key])?$this->meta[$key]:$def;
	}

	public function setLayout(string $layout){ 	
		$this->layout = $layout;
	}

	public function getLayout(){
		return $this->layout;
	}

	public function setDefault(){
		$this->meta = array(
			'title' => 'Сайт',
			'description' => '',
		);
		$this->f3->set('content.header','/include/header.php');
		$this->f3->set('content.footer','/include/footer.php');
	}

	public function showHead(){
		echo self::BUFFER['head'];
	}

	public function showFooter(){
		echo self::BUFFER['footer'];
	}

	public function renderBuffer($html, $file){
		$headHtml = '';
		$footerHtml = '';
		$headHtml .= $this->generateMeta();
		$headHtml .= $this->assets->renderCss();
		$headHtml .= $this->assets->renderJs();
		$buffer = [];
		$buffer[] = '/'.self::BUFFER['head'].'/';
		$buffer[] = '/'.self::BUFFER['footer'].'/';
		return preg_replace($buffer, [$headHtml, $footerHtml], $html);
	}

	public function render(array $arParams = []){
		$template = template();
		$template->afterrender([$this, 'renderBuffer'],$this->layout);
        return $template->render($this->layout,$arParams);
	}

	public function content(string $val){
		$content = $this->f3->get('content');
		if($val && is_array($content) && isset($content[$val])){
			return $content[$val];
		} else {
			$this->f3->set('block_not_found',$val);
			return '/include/block404.php';
		}
	}

	public function setContent($key,$val){
		$key = preg_replace('/[^a-zA-Z_-]*/', '', $key);
		if(!empty($key)){
			$this->f3->set('content.'.$key,$val);
		}
	}

	public function generateMeta(){
		$text = [];
		if(!empty($this->meta['description'])){
			$text[] = '<meta name="description" content="'.$this->meta['description'].'">';
		}
		return implode("\n", $text);
	}
}