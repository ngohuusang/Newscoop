<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl.txt
 */

namespace Newscoop\Entity\Repository;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Newscoop\Entity\Feedback;
use Newscoop\Entity\User;
use Newscoop\Entity\Article;
use Newscoop\Entity\Section;
use Newscoop\Datatable\Source as DatatableSource;

/**
 * Feedback repository
 */
class FeedbackRepository extends DatatableSource
{

    /**
     * Get new instance of the comment
     *
     * @return \Newscoop\Entity\Feedback
     */
    public function getPrototype()
    {
        return new Feedback;
    }

    /**
     * Method for saving a feedback
     *
     * @param \Newscoop\Entity\Feedback $entity
     * @param array $values
     * @return Feedback \Newscoop\Entity\Feedback
     */
    public function save(Feedback $entity, $values)
    {
        // get the entity manager
        $em = $this->getEntityManager();
        $user = $em->getReference('Newscoop\Entity\User', $values['user']);
        $section = $em->getReference('Newscoop\Entity\Section', $values['section']);
        $article = $em->getReference('Newscoop\Entity\Article', array(
			'language' => $values['language'],
			'number' => $values['article'],
		));
		
        $entity->setUser($user);
        $entity->setSection($section);
        $entity->setArticle($article);
        $entity->setSubject($values['subject']);
        $entity->setMessage($values['message']);
        $entity->setUrl($values['url']);
        $entity->setTimeCreated($values['time_created']);
        $entity->setStatus($values['status']);
        
        $em->persist($entity);
        return $entity;
    }

    /**
     * Method for setting status
     *
     * @param array $feedbacks Feedback identifiers
     * @param string $status
     * @return void
     */
    public function setStatus(array $feedbacks, $status)
    {
        foreach ($feedbacks as $feedback) {
            $this->setFeedbackStatus($this->find($feedback), $status);
        }
    }

    /**
     * Method for setting status for a feedback message
     *
     * @param \Newscoop\Entity\Feedback $feedback
     * @param  string $status
     * @return void
     */
    private function setFeedbackStatus(Feedback $feedback, $status)
    {
        $em = $this->getEntityManager();
        if ($status == 'deleted') {
            $em->remove($feedback);
        } else {
            $feedback->setStatus($status);
            $em->persist($feedback);
        }
    }

    /**
     * Get data for table
     *
     * @param array $params
     * @param array $cols
     * @return Comment[]
     */
    public function getData(array $params, array $cols)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->from('Newscoop\Entity\User', 's');
        $andx = $qb->expr()->andx();
        $andx->add($qb->expr()->eq('e.user', new Expr\Literal('s.id')));

        if (!empty($params['sSearch'])) {
            $this->buildWhere($cols, $params['sSearch'], $qb, $andx);
        }

        if (!empty($params['sFilter'])) {
            $this->buildFilter($cols, $params['sFilter'], $qb, $andx);
        }

        // sort
        if (isset($params['iSortCol_0'])) {
            $colsIndex = array_keys($cols);
            $sortId = $params['iSortCol_0'];
            $sortBy = $colsIndex[$sortId];
            $dir = $params['sSortDir_0'] ? : 'asc';
            switch ($sortBy) {
                case 'user':
                    $qb->orderBy('s.name', $dir);
                    break;
                case 'message':
                    $qb->orderBy('e.message', $dir);
                    break;
                case 'url':
                    $qb->orderBy('e.url', $dir);
                    break;
                case 'index':
                    $qb->orderBy('e.time_created', $dir);
                    break;
                default:
                    $qb->orderBy('e.' . $sortBy, $dir);
            }
        }

        $qb->where($andx);

        // limit
        if (isset($params['iDisplayLength'])) {
            $qb->setFirstResult((int)$params['iDisplayStart'])->setMaxResults((int)$params['iDisplayLength']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Build where condition
     *
     * @param array $cols
     * @param string $search
     * @return Doctrine\ORM\Query\Expr
     */
    protected function buildWhere(array $cols, $search, $qb, $andx)
    {
        $orx = $qb->expr()->orx();
        $orx->add($qb->expr()->like('s.name', $qb->expr()->literal("%{$search}%")));
        $orx->add($qb->expr()->like('e.subject', $qb->expr()->literal("%{$search}%")));
        $orx->add($qb->expr()->like('e.message', $qb->expr()->literal("%{$search}%")));
        return $andx->add($orx);
    }

    /**
     * Build filter condition
     *
     * @param array $cols
     * @param array $filter
     * @param $qb
     * @param $andx
     * @return Doctrine\ORM\Query\Expr
     */
    protected function buildFilter(array $cols, array $filter, $qb, $andx)
    {
        foreach ($filter as $key => $values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            $orx = $qb->expr()->orx();
            switch ($key) {
                case 'status':
                    $mapper = array_flip(Feedback::$status_enum);
                    foreach ($values as $value) {
                        $orx->add($qb->expr()->eq('e.status', $mapper[$value]));
                    }
                    break;
            }
            $andx->add($orx);
        }
        return $andx;
    }

    /**
     * Flush method
     */
    public function flush()
    {
        $this->getEntityManager()->flush();
    }
}
