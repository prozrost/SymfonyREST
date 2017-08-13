<?php

namespace AppBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class UserController extends FOSRestController
{

    /**
     * @ApiDoc(
     *     description = "get user with dome id",
     *     requirements ={
     *     {
     *       "name" = "id",
     *       "type" = "integer",
     *       "requirement" = "\d+",
     *       "description" = "id of object what you need to fetch"
     *     }
     *     }
     *     )
     */
    public function getUserAction($id)
    {
        $singleresult = $this->getDoctrine()->getRepository('AppBundle:User')->find($id);
        if ($singleresult === null) {
            return new View("user not found", Response::HTTP_NOT_FOUND);
        }
        $json_result = json_encode($singleresult->name);
        return $json_result;
    }
    /**
     * @ApiDoc(
     *     description = "register user in system",
     *     requirements ={
     *     {
     *       "name" = "request",
     *       "type" = "Request",
     *       "description" = "$request object what we need to parse"
     *     }
     *     }
     *     )
     */
    public function postUserAction(Request $request)
    {
        /** @var $formFactory \FOS\UserBundle\Form\Factory\FactoryInterface */
        $formFactory = $this->get('fos_user.registration.form.factory');
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');
        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $user = $userManager->createUser();
        $user->setEnabled(true);

        $event = new \FOS\UserBundle\Event\GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(\FOS\UserBundle\FOSUserEvents::REGISTRATION_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $formFactory->createForm();
        $form->setData($user);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $event = new \FOS\UserBundle\Event\FormEvent($form, $request);
            $dispatcher->dispatch(\FOS\UserBundle\FOSUserEvents::REGISTRATION_SUCCESS, $event);

            $userManager->updateUser($user);

            if (null === $response = $event->getResponse()) {
                $url = $this->generateUrl('fos_user_registration_confirmed');
                $response = new \Symfony\Component\HttpFoundation\RedirectResponse($url);
            }

            $dispatcher->dispatch(\FOS\UserBundle\FOSUserEvents::REGISTRATION_COMPLETED, new \FOS\UserBundle\Event\FilterUserResponseEvent($user, $request, $response));

            $view = $this->view(array('token' => $this->get("lexik_jwt_authentication.jwt_manager")->create($user)), Codes::HTTP_CREATED);

            return $this->handleView($view);
        }

        $view = $this->view($form, Codes::HTTP_BAD_REQUEST);
        return $this->handleView($view);
    }
    /**
     * @ApiDoc(
     *     description = "delete user with dome id",
     *     requirements ={
     *     {
     *       "name" = "id",
     *       "type" = "integer",
     *       "requirement" = "\d+",
     *       "description" = "id of object what you need to delete"
     *     }
     *     }
     *     )
     */
    public function deleteUserAction($id)
    {
        $sn = $this->getDoctrine()->getManager();
        $user = $sn->getRepository('AppBundle:User')->find($id);
        if (empty($user)) {
            return new View("user not found", Response::HTTP_NOT_FOUND);
        }
        else {
            $sn->remove($user);
            $sn->flush();
        }
        return new View("deleted successfully", Response::HTTP_OK);
    }
}