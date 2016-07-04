<?php

namespace WCS\CantineBundle\Entity;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Scheduler\Component\DateContainer\Day;
use Scheduler\Component\DateContainer\Period;
use Scheduler\Component\DateContainer\WeekStats;

/**
 * LunchRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class LunchRepository extends ActivityRepositoryAbstract
{
    /**
     * @var OptionsResolver
     */
    private $weekMealResolver;

    /**
     * LunchRepository constructor.
     *
     * @inheritdoc
     */
    public function __construct($em, \Doctrine\ORM\Mapping\ClassMetadata $class)
    {
        $this->weekMealResolver = new OptionsResolver();
        $this->configureWeekMealOptions();

        parent::__construct($em, $class);
    }

    /**
     * Set up the configurations of options
     * passed as argument into the following methods of this class :
     * - getWeekDates
     * - getWeekMeals
     */
    private function configureWeekMealOptions()
    {
        $this->weekMealResolver->setDefined(array(
            'date_day',
            'without_pork',
            'enable_next_week',
            'days_ofweek_off',
            'dates_off'
        ));

        $this->weekMealResolver->setAllowedTypes('date_day', \DateTimeInterface::class);

        $this->weekMealResolver->setDefaults(array(
            'without_pork'      => false,
            'enable_next_week'  => false,
            'days_ofweek_off'   => array(Day::WEEK_WEDNESDAY),
            'dates_off'          => array() // \DateTime[]
        ));

        $this->weekMealResolver->setRequired(array(
            'without_pork',
            'date_day'
        ));
    }

    /**
     * Return the week dates (first day, last day, list of days) given options passed
     *
     * @param array $options with the following keys :
     * - date_day : \DateTimeInterface of the day of reference
     * - enable_next_week : boolean - True if the week to return is the next following
     *    the week containing the day of reference
     * - days_ofweek_off [optional] : Day::constants[] list of days generally off in the week.
     * - dates_off [optional] : \DateTime[] list of dates off in the week
     *
     * @return array associative array with the following keys :
     * - first_day : \DateTimeImmutable of the first day of the week (the monday)
     * - last_day : \DateTimeImmutable of the last day of the week (the friday)
     * - days : \DateTimeImmutable[] index array of days in the week that take in account
     *  the closed days passed in options
     */
    public function getWeekDates($options)
    {
        $options = $this->weekMealResolver->resolve($options);

        if (true === $options['enable_next_week']) {
            $firstDayFormat = ' next monday';
            $lastDayFormat  = ' next friday';
        }
        else {
            $firstDayFormat = ' monday this week';
            $lastDayFormat  = ' friday this week';
        }

        $firstDay   = new \DateTimeImmutable( $options['date_day']->format('Y-m-d') . $firstDayFormat );
        $lastDay    = new \DateTimeImmutable( $firstDay->format('Y-m-d') . $lastDayFormat );

        $periode = new Period($firstDay, $lastDay);
        $days = [];
        foreach($periode->getDayIterator() as $date) {

            if (false === \in_array(Day::getWeekDayFrom($date), $options['days_ofweek_off'])
                && false === \in_array($date, $options['dates_off'])
            ) {
                $days[] = $date;
            }
        }

        return array('first_day' => $firstDay, 'last_day' => $lastDay, 'days' => $days);
    }


    /**
     * Return the week dates (first day, last day, list of days) given options passed
     *
     * @param array $options with the following keys :
     * - date_day : \DateTimeInterface of the day of reference
     * - enable_next_week : boolean - True if the week to return is the next following
     *    the week containing the day of reference
     * - days_ofweek_off [optional] : Day::constants[] list of days generally off in the week.
     * - dates_off [optional] : \DateTime[] list of dates off in the week
     * - without_pork : boolean :
     *      true if the week must return only lunches without pork
     *      false if the week must return only regular lunches.
     *
     * @return WeekStats statistics of the week for lunches.
     */
    /**
     * @param $options
     * @return WeekStats
     */
    public function getWeekMeals($options)
    {
        $dates = $this->getWeekDates($options);

        $statsLunch = new WeekStats();

        foreach($dates['days'] as $day) {
            // compte le nombre d'élèves inscrits à la cantine
            // qui ne sont pas :
            // en sortie scolaire ce jour là
            // inscrits en voyage scolaire (non annulé) ce jour là
            $query = $this->createQueryBuilder('l')
                ->select('COUNT(l)')
                ->join('l.eleve', 'e')
                ->where('DATE(l.date) = :date_day')
                ->andWhere('e.regimeSansPorc = :pork')
                ->setParameter(':date_day', $day->format('Y-m-d'))
                ->setParameter(':pork', $options['without_pork']);

            $query = $this->excludePupilsTravellingAt($query, 'e', $day);

            $totalCurrentDay = $query->getQuery()->getSingleScalarResult();

            $statsLunch->setTotalDay(Day::getWeekDayFrom($day), $totalCurrentDay);
        }

        return $statsLunch;
    }


    /**
     * Return the list of lunches for the given day in options
     *
     * @param School $school
     * @param array $options
     * @return array
     */
    public function getDayList($options)
    {
        $school = $options['school'];
        $day    = $options['date_day']->format('Y-m-d');

        $query = $this
            ->createQueryBuilder('l')
            ->join('l.eleve', 'e')
            ->join('e.division', 'd')
            ->where('DATE(l.date) = :day')
            ->andWhere('d.school = :place')
            ->orderBy('d.grade')
            ->addOrderBy('d.headTeacher')
            ->addOrderBy('e.nom')
            ->setParameter(':day', $day)
            ->setParameter(':place', $school);

        $query = $this->excludePupilsTravellingAt($query, 'e', $options['date_day']);

        return $query->getQuery()->getResult();
    }

    /**
     * Retrieve a lunch for a given date and pupil
     *
     * @param string $date
     * @param Eleve $eleve
     * @return array result of the query
     */
    public function findByDateAndEleve($date, $eleve)
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT l FROM WCSCantineBundle:Lunch l WHERE l.date = :date AND l.eleve = :eleve'
            )
            ->setParameter(':date', $date)
            ->setParameter(':eleve', $eleve)
            ->getResult();
    }


    /**
     * Delete all lunches for one pupil
     *
     * @param Eleve $eleve
     * @return mixed
     */
    public function removeByEleve(Eleve $eleve)
    {
        return $this->getEntityManager()
            ->createQuery(
                'DELETE WCSCantineBundle:Lunch l WHERE l.eleve = :eleve'
            )
            ->setParameter(':eleve', $eleve)
            ->execute();
    }

    /**
     * @return integer the number of lunches
     */
    public function count()
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
