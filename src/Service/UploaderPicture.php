<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class UploaderPicture {

    public function __construct(
        private Filesystem $fs,
        private $profileFolder,
        private $profileFolderPublic
        )
    {
        
    }

    public function uploadProfileImage($picture, $oldPicture = null) {
        $folder = $this->profileFolder;
        $ext = $picture->guessExtension() ?? 'bin';
        $filename = bin2hex(random_bytes(10)) . '.' . $ext;
        $picture->move($folder, $filename);
        if($oldPicture !== 'profiles/no_picture.jpeg') {
            $this->fs->remove($folder . '/' . pathinfo($oldPicture, PATHINFO_BASENAME));
        }

        return $this->profileFolderPublic . '/' . $filename;
    }
}