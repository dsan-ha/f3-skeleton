<?
namespace App\Component\DS;

use App\Component\BaseComponent;

class TestComponent extends BaseComponent {
    public function execute(): void
    {
        $this->arResult['TEST'] = array('name' => '', 'folder' => '');
    }
}
