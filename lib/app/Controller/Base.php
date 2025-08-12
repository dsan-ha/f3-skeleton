<?

namespace App\Controller;

use Core\Assets;

final class Base extends ControllerBase
{
    protected function initController(): void
    {

    }
    
    public function index($_, $params)
    {
        app()->setContent('body','pages/index.php');
        $this->render();
    }

    protected function middleware(): void
    {

    }

    protected function afterRender(array $arParams = []): void
    {

    }
}