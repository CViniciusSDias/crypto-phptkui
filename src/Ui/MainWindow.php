<?php

declare(strict_types=1);

namespace CViniciusSDias\Crypto\Ui;

use Tkui\Application;
use Tkui\Dialogs\DirectoryDialog;
use Tkui\Dialogs\OpenFileDialog;
use Tkui\Layouts\Pack;
use Tkui\Widgets\Buttons\Button;
use Tkui\Widgets\Consts\Anchor;
use Tkui\Widgets\Consts\Orient;
use Tkui\Widgets\Container;
use Tkui\Widgets\Frame;
use Tkui\Widgets\Label;
use Tkui\Widgets\LabelFrame;
use Tkui\Widgets\Menu\Menu;
use Tkui\Widgets\Menu\MenuItem;
use Tkui\Widgets\PanedWindow;
use Tkui\Widgets\Text\Text;
use function sodium_crypto_box_keypair;
use function sodium_crypto_box_publickey;
use function sodium_crypto_box_secretkey;
use function sodium_crypto_box_keypair_from_secretkey_and_publickey;
use function sodium_bin2hex;
use function sodium_hex2bin;
use function sodium_crypto_box_seal;
use function sodium_crypto_box_seal_open;

class MainWindow extends \Tkui\Windows\MainWindow
{
    private string $publicKeyPath = '';
    private string $secretKeyPath = '';
    private Text $messageText;
    private Text $resultText;

    public function __construct(Application $app, string $title)
    {
        parent::__construct($app, $title);
    }

    public function draw(): void
    {
        $this->defineApplicationMenu();

        $panedWindow = new PanedWindow($this, ['orient' => Orient::ORIENT_HORIZONTAL]);
        $secretKeyFrame = $this->secretKeyFrame($panedWindow);
        $publicKeyFrame = $this->publicKeyFrame($panedWindow);
        $messageFrame = $this->messageFrame();
        $buttonsFrame = $this->buttonsFrame();
        $resultFrame = $this->resultFrame();

        $panedWindow
            ->add($secretKeyFrame, 1)
            ->add($publicKeyFrame, 1);

        $this->pack([$panedWindow, $messageFrame, $buttonsFrame, $resultFrame], ['padY' => 10]);

        $windowManager = $this->getWindowManager();
        $windowManager->setSize(480, 380);
        $windowManager->setMinSize(480, 380);
    }

    private function defineApplicationMenu(): void
    {
        $menu = new Menu($this);

        $menu->addMenu('Keys')
            ->addItem(new MenuItem('Create keypair', function () {
                $dlg = new DirectoryDialog($this, ['title' => 'Where to save the keys']);
                $dlg->onSuccess(function (string $dir) {
                    $keyPair = sodium_crypto_box_keypair();

                    file_put_contents($dir . DIRECTORY_SEPARATOR . 'public.key', sodium_crypto_box_publickey($keyPair));
                    file_put_contents($dir . DIRECTORY_SEPARATOR . 'private.key', sodium_crypto_box_secretkey($keyPair));
                });
                $dlg->onCancel(fn() => var_dump('Erro'));

                $dlg->showModal();
            }));
        $this->setMenu($menu);
    }

    private function secretKeyFrame(Container $parent = null): LabelFrame
    {
        $secretKeyFrame = new LabelFrame($parent ?? $this, 'Select your secret key', ['labelanchor' => Anchor::ANCHOR_N]);

        $findSecretKeyButton = new Button($secretKeyFrame, 'Find');

        $secretKeyFrame->pack($findSecretKeyButton, ['side' => Pack::SIDE_TOP, 'padx' => 2, 'pady' => 2]);

        $selectedKeyLabel = new Label($secretKeyFrame, 'Secret key not selected yet', ['anchor' => Anchor::ANCHOR_CENTER]);
        $selectedKeyLabel->width = 35;
        $secretKeyFrame->pack($selectedKeyLabel);

        $secretKeyDialog = new OpenFileDialog($this, ['title' => 'Choose your secret key file']);
        $secretKeyDialog->addFileType('Key', '.key');
        $secretKeyDialog->addFileType('All files', '*');

        $secretKeyDialog->onSuccess(fn(string $file) => $selectedKeyLabel->text = $this->secretKeyPath = $file);

        $findSecretKeyButton->onClick([$secretKeyDialog, 'showModal']);
        return $secretKeyFrame;
    }

    private function publicKeyFrame(Container $parent = null): LabelFrame
    {
        $publicKeyFrame = new LabelFrame($parent ?? $this, 'Select the other person\'s public key', ['labelanchor' => Anchor::ANCHOR_N]);

        $findPublicKeyButton = new Button($publicKeyFrame, 'Find');

        $publicKeyFrame->pack($findPublicKeyButton);

        $selectedKeyLabel = new Label($publicKeyFrame, 'Public key not selected yet', ['anchor' => Anchor::ANCHOR_CENTER]);
        $selectedKeyLabel->width = 35;
        $publicKeyFrame->pack($selectedKeyLabel, ['side' => Pack::SIDE_BOTTOM, 'fill' => Pack::FILL_X, 'expand' => true]);

        $publicKeyDialog = new OpenFileDialog($this, ['title' => 'Choose the public key file']);
        $publicKeyDialog->addFileType('Key', '.key');
        $publicKeyDialog->addFileType('All files', '*');

        $publicKeyDialog->onSuccess(fn(string $file) => $selectedKeyLabel->text = $this->publicKeyPath = $file);

        $findPublicKeyButton->onClick([$publicKeyDialog, 'showModal']);
        return $publicKeyFrame;
    }

    private function messageFrame(): LabelFrame
    {
        $messageFrame = new LabelFrame($this, 'Message to encrypt/decrypt');

        $this->messageText = new Text($messageFrame, ['height' => 3, 'width' => 54]);
        $messageFrame->pack($this->messageText);

        return $messageFrame;
    }

    private function resultFrame(): LabelFrame
    {
        $labelFrame = new LabelFrame($this, 'Result');
        $this->resultText = new Text($labelFrame, ['height' => 5, 'width' => 54]);
        $labelFrame->pack($this->resultText, [ 'pady' => 2 ]);

        return $labelFrame;
    }

    public function buttonsFrame(): Frame
    {
        $frame = new Frame($this);

        $encryptButton = new Button($frame, 'Encrypt');
        $encryptButton->onClick(function (): void {
            $plainText = $this->messageText->getContent();
            $cypher = sodium_crypto_box_seal($plainText, file_get_contents($this->publicKeyPath));

            $this->resultText->setContent(sodium_bin2hex($cypher));
        });

        $decryptButton = new Button($frame, 'Decrypt');
        $decryptButton->onClick(function (): void {
            $message = sodium_hex2bin(trim($this->messageText->getContent()));
            $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                file_get_contents($this->secretKeyPath),
                file_get_contents($this->publicKeyPath),
            );

            $plainText = sodium_crypto_box_seal_open($message, $keyPair);

            $this->resultText->setContent($plainText ?: 'Erro ao decifrar');
        });

        $frame->borderWidth = 1;
        $frame->pack($encryptButton, ['side' => Pack::SIDE_LEFT]);
        $frame->pack($decryptButton, ['side' => Pack::SIDE_RIGHT]);

        return $frame;
    }
}
