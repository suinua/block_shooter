<?php


namespace block_shooter\form;


use block_shooter\service\CorePVPGameService;
use block_shooter\service\SoloGameService;
use form_builder\models\simple_form_elements\SimpleFormButton;
use form_builder\models\simple_form_elements\SimpleFormImage;
use form_builder\models\simple_form_elements\SimpleFormImageType;
use form_builder\models\SimpleForm;
use pocketmine\Player;

class JoinGameForm extends SimpleForm
{

    public function __construct() {
        parent::__construct("", "", [
            new SimpleFormButton(
                "Solo",
                new SimpleFormImage(SimpleFormImageType::Path(), "textures/ui/dressing_room_animation.png"),
                function (Player $player) {
                    SoloGameService::randomJoin($player);
                }
            ),
            new SimpleFormButton(
                "Core",
                new SimpleFormImage(SimpleFormImageType::Path(), "textures/ui/dressing_room_skins.png"),
                function (Player $player) {
                    CorePVPGameService::randomJoin($player);
                }
            ),
        ]);
    }

    function onClickCloseButton(Player $player): void {
        // TODO: Implement onClickCloseButton() method.
    }
}