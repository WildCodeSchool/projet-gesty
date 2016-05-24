<?php

namespace WCS\CantineBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Application\Sonata\UserBundle\Entity\User;
use WCS\CantineBundle\Entity\Eleve;
use WCS\CantineBundle\Form\Handler\EleveHandler;
use WCS\CantineBundle\Form\Model\EleveFormEntity;
use WCS\CantineBundle\Form\Type\EleveEditType;
use WCS\CantineBundle\Form\Type\EleveType;

/**
 * Eleve controller.
 *
 */
class EleveController extends Controller
{
    /**
     * Lists all Eleve entities.
     *
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('WCSCantineBundle:Eleve')->findAll();

        return $this->render('WCSCantineBundle:Eleve:index.html.twig', array(
            'entities' => $entities,
        ));
    }

    /**
     * Creates a new Eleve entity.
     *
     */
    public function createAction(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // Enregistre les élèves en BDD
        $entity = new EleveFormEntity();
        $form = $this->createCreateForm($entity);
        
        $handler = new EleveHandler($form, $request, $this->getDoctrine()->getManager(), $this->getUser());
        if ($handler->process($entity)) {
            return $this->redirect($this->generateUrl('wcs_cantine_dashboard'));
        }

        return $this->render('WCSCantineBundle:Eleve:new.html.twig', array(
            'entity' => $entity,
            'form' => $form->createView()
        ));
    }

    /**
     * Creates a form to create a Eleve entity.
     *
     * @param Eleve $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createCreateForm(EleveFormEntity $eleve)
    {
        $form = $this->createForm(new EleveType(), $eleve, array(
            'action' => $this->generateUrl('eleve_create'),
            'method' => 'POST',
        ));


        return $form;
    }

    /**
     * Finds and displays a Eleve entity.
     *
     */
    public function showAction($id)
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        $eleve = $em->getRepository('WCSCantineBundle:Eleve')->find($id);

        if (!$eleve) {
            return $this->redirectToRoute('wcs_cantine_dashboard');
        }

        return $this->render('WCSCantineBundle:Eleve:show.html.twig', array(
            'entity' => $eleve,
        ));
    }

    /**
     * Displays a form to edit an existing Eleve entity.
     *
     */
    public function editAction($id)
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // Récupère les informations de l'élève
        $em = $this->getDoctrine()->getManager();

        $eleve = $em->getRepository('WCSCantineBundle:Eleve')->find($id);
        if (!$eleve || !$eleve->isCorrectParentConnected($user)) {
            return $this->redirectToRoute('wcs_cantine_dashboard');
        }

        $editForm = $this->createEditForm($eleve);

        return $this->render('WCSCantineBundle:Eleve:edit.html.twig', array(
            'eleve' => $eleve,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Creates a form to edit a Eleve entity.
     *
     * @param Eleve $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createEditForm(Eleve $entity)
    {
        $form = $this->createForm(new EleveEditType($this->getDoctrine()->getManager()), $entity, array(
            'action' => $this->generateUrl('eleve_update', array('id' => $entity->getId())),
            'method' => 'POST',
        ));

        return $form;
    }

    /**
     * Edits an existing Eleve entity.
     *
     */
    public function updateAction(Request $request, $id)
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('WCSCantineBundle:Eleve')->find($id);

        if (!$entity || !$entity->isCorrectParentConnected($user)) {
            return $this->redirectToRoute('wcs_cantine_dashboard');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);



        if ($editForm->isValid()) {
            $oldLunches = $em->getRepository('WCSCantineBundle:Lunch')->findByEleve($entity);
            foreach($oldLunches as $lunch)
            {
                if (!$entity->getLunches()->contains($lunch))
                    $em->remove($lunch);
            }
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('wcs_cantine_dashboard'));
        }


        return $this->render('WCSCantineBundle:Eleve:edit.html.twig', array(
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a Eleve entity.
     *
     */
    public function deleteAction(Request $request, $id)
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createDeleteForm($id);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('WCSCantineBundle:Eleve')->find($id);

            if (!$entity || !$entity->isCorrectParentConnected($user)) {
                return $this->redirectToRoute('wcs_cantine_dashboard');
            }

            $em->remove($entity);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('eleve'));
    }

    /**
     * Creates a form to delete a Eleve entity by id.
     *
     * @param mixed $id The entity id
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm($id)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('eleve_delete', array('id' => $id)))
            ->setMethod('DELETE')
            ->add('submit', 'submit', array('label' => 'Delete'))

            ->getForm();

    }

    /**
     * Generate calendar
     */
    /*
    public function generateCalendar(\DateTime $start, \DateTime $end)
    {
        $return = array();
        $calendrier = $start;

        while ($calendrier <= $end) {
            $y = date_format($calendrier, ('Y'));
            $m = date_format($calendrier, ('m'));
            $d = date_format($calendrier, ('d'));
            $w = str_replace('0', '7', date_format($calendrier, ('w')));
            $return[$y][$m][$d] = $w;
            $calendrier->add(new \DateInterval('P1D'));
        }
        return $return;
    }
    */

    /**
     * Generate range date
     */
    /*
    private function getHolidays($start, $end)
    {
        $array = [];
        $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), new \DateTime($end));

        foreach ($period as $date) {
            $array[] = date_format($date, ('Y-m-d'));
        }
        return $array;
    }
    */

    /**
     * Affiche le dashboard
     *
     * @param Request       contient les paramètres passés en URL
     * @return Response     renvoit une reponse HTTP après rendu du template dashboard
     */
    public function dashboardAction(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }


        $em = $this->getDoctrine()->getEntityManager();
        $children = $em->getRepository("WCSCantineBundle:Eleve")->findChildren($user);

        $filesArray = array();
        $filesArray[User::type_Domicile]    = array(
            'libelle_justif' => 'Justificatif de domicile',
            'exists' => is_file($user->getAbsolutePathDomicile())
        );

        $filesArray[User::type_Prestations] = array(
            'libelle_justif' => 'Justificatif de prestations CAF',
            'exists' => is_file($user->getAbsolutePathPrestations())
        );

        $filesArray[User::type_Salaire1]    = array(
            'libelle_justif' => 'Justificatif de salaire 1',
            'exists' => is_file($user->getAbsolutePathSalaire1())
        );

        $filesArray[User::type_Salaire2]    = array(
            'libelle_justif' => 'Justificatif de salaire 2',
            'exists' => is_file($user->getAbsolutePathSalaire2())
        );

        $filesArray[User::type_Salaire3]    = array(
            'libelle_justif' => 'Justificatif de salaire 3',
            'exists' => is_file($user->getAbsolutePathSalaire3())
        );

        return $this->render('WCSCantineBundle:Eleve:dashboard.html.twig', array(
            'user' => $user,
            'children' => $children,
            'files'=>$filesArray
        ));
    }


    public function updateDate($query)
    {
        return $this->getDoctrine()->getManager()
            ->createQuery(
                'UPDATE WCSCantineBundle:Eleve SET dates'
            )
            ->getResult();
    }
}
