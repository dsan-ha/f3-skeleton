<?
namespace App;

use App\Utils\Assets;   
use App\F3;
use App\Component\ComponentManager;
/**
 * 
 */
final class App extends \Prefab
{
	private $f3; 
	private $meta; 
	public $component; 
	private $layout = 'layout.php';
	const BUFFER = ['head' => '{{__headBufferf3}}', 'footer' => '{{__footerBufferf3}}'];
	
	function __construct()
	{
		$this->f3 = F3::instance();
		$this->component = new ComponentManager($this->f3);
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
			'description' => 'Описание сайта',
		);
		$this->f3->set('content.header','include/header.php');
		$this->f3->set('content.footer','include/footer.php');
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
		$headHtml .= Assets::instance()->renderCss();
		$headHtml .= Assets::instance()->renderJs();
		$buffer = [];
		$buffer[] = '/'.self::BUFFER['head'].'/';
		$buffer[] = '/'.self::BUFFER['footer'].'/';
		return preg_replace($buffer, [$headHtml, $footerHtml], $html);
	}

	public function render(array $arParams = []){
		$template = template();
		$template->afterrender([$this, 'renderBuffer'],$this->layout);
        echo $template->render($this->layout,$arParams);
	}

	public function content($val){
		$content = $this->f3->get('content');
		if(is_array($content) && isset($content[$val])){
			return $content[$val];
		} else {
			throw new Exception('<span class="color:red;font-weight:bold;">Content nofound: '.$val.'</span>', 1);
		}
	}

	public function setContent($key,$val){
		$key = preg_replace('/[^a-zA-Z_-]*/', '', $key);
		if(!empty($key)){
			$this->f3->set('content.'.$key,$val);
		}
	}
}