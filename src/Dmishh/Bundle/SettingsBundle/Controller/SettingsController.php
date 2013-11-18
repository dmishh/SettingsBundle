<?php

/**
 * This file is part of the DmishhSettingsBundle package.
 *
 * (c) 2013 Dmitriy Scherbina <http://dmishh.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dmishh\Bundle\SettingsBundle\Controller;

use Dmishh\Bundle\SettingsBundle\Entity\UserInterface;
use Journalist\CoreBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SettingsController extends Controller
{
    /**
     * @Route("/manage/{user_id}", name="dmishh_settings", requirements={"user_id" = "\d+"})
     */
    public function manageSettingsAction(Request $request, $user_id = null)
    {
        $user = $this->getUserObject($user_id);
        $this->verifyCredentials($user);

        $settings = array_map(function ($value) { return array('value' => $value); }, $this->get('dmishh_settings.manager')->all($user));
        $form = $this->createForm('dmishh_settings', $settings, array('disabled_settings' => $this->getDisabledSettings($user)));

        if ($request->isMethod('post')) {
            $form->bind($request);

            if ($form->isValid()) {
                $settingsData = array();
                foreach ($form->getData() as $name => $data) {
                    $settingsData[$name] = $data['value'];
                }

                $this->get('dmishh_settings.manager')->setMany($settingsData, $user);

                return $this->redirect($request->getUri());
            }
        }

        return $this->render(
            $this->container->getParameter('dmishh_settings.manager.template'),
            array(
                'settings_form' => $form->createView(),
                'layout' => $this->container->getParameter('dmishh_settings.manager.layout'),
            )
        );
    }

    /**
     * Override this method and throw 403 or 404 esceptions depending on your business logic
     *
     * @param UserInterface $user
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    protected function verifyCredentials(UserInterface $user = null)
    {
        $securitySettings = $this->container->getParameter('dmishh_settings.manager.security');

        if (!empty($securitySettings['manage_settings_role'])) {
            if ($user === null) {
                if (!$this->get('security.context')->isGranted($securitySettings['manage_settings_role'])) {
                    throw new AccessDeniedException('You are not allowed to edit global settings');
                }
            } else {
                if (!$this->get('security.context')->isGranted($securitySettings['manage_settings_role']) &&
                    !($securitySettings['users_can_manage_own_settings'] && $this->getUser() == $user)
                ) {
                    throw new AccessDeniedException('You are not allowed to edit user settings');
                }
            }
        }
    }

    /**
     * @param UserInterface $user
     * @return array
     */
    protected function getDisabledSettings(UserInterface $user = null)
    {
        return array();
    }

    /**
     * @param int $userId
     * @return UserInterface|null
     */
    protected function getUserObject($userId = null)
    {
        $userClass = $this->container->getParameter('dmishh_settings.manager.user_class');
        return $userId === null ? null : $this->get('doctrine')->getManager()->getRepository($userClass)->find($userId);
    }
}