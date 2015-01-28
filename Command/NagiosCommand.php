<?php
namespace Arii\JIDBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class NagiosCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('nagios')
            ->setDescription('Affiche l\'état d\'un groupe de traitements.')
            ->addArgument(
                'filter',
                InputArgument::OPTIONAL,
                'Filtre des traitements'
            )
            ->addOption(
               'spooler',
               null,
               InputOption::VALUE_NONE,
               'Si défini, on ne traite que le moteur indiqué.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filter = $input->getArgument('filter');
        if ($input->getOption('spooler')) {
            $spooler = $input->getOption('spooler');
        }

        $dhtmlx = $this->getContainer()->get('arii_core.dhtmlx');
        $data = $dhtmlx->Connector('data');
        
        $sql = $this->container->get('arii_core.sql');
        
        $Fields = array (
            '{spooler}'     => 'SPOOLER_ID',
            'STOPPED'    => 1 );
        $qry = $sql->Select(array('sh.SPOOLER_ID','sh.PATH as JOB_NAME','sh.STOPPED','sh.NEXT_START_TIME')) 
                .$sql->From(array('SCHEDULER_JOBS sh'))
                .$sql->Where($Fields)
                .$sql->OrderBy(array('sh.SPOOLER_ID','sh.PATH'));

        $res = $data->sql->query( $qry );
        $Stopped = array();
        while ($line = $data->sql->get_next($res)) {
             $job = $line['SPOOLER_ID'].'/'.$line['JOB_NAME'];
             $Stopped[$job] = 1;
        }
        
        $Fields = array (
            '{spooler}'     => 'SPOOLER_ID',
            'JOB_NAME'    => $filter );
        $qry = $sql->Select(array('h.SPOOLER_ID','h.JOB_NAME','h.ERROR','h.END_TIME','h.CAUSE',
            'oh.JOB_CHAIN','oh.ORDER_ID','osh.STATE', 'oh.STATE as ORDER_STATE','oh.END_TIME as ORDER_END_TIME')) 
               .$sql->From(array('SCHEDULER_HISTORY h'))
               .$sql->LeftJoin('SCHEDULER_ORDER_STEP_HISTORY osh',array('h.ID','osh.TASK_ID'))
               .$sql->LeftJoin('SCHEDULER_ORDER_HISTORY oh',array('osh.HISTORY_ID','oh.HISTORY_ID'))
               .$sql->Where($Fields)
               .$sql->OrderBy(array('h.START_TIME desc'));

        $res = $data->sql->query( $qry );
        $Done = array();
        $Errors = array();
        $ToCheck = array();
        $Fatal_orders = array(); // Ordres suspendus
        $Fatal_jobs = array(); // Jobs stoppés   
        while ($line = $data->sql->get_next($res)) {
            // On prend le dernier statut
            $spooler = $line['SPOOLER_ID'];
            $job     = $spooler.'/'.$line['JOB_NAME'];
            if ((!isset($Done[$job])) and ($line['END_TIME']!='') ) {
//                print $line['SPOOLER_ID']." ".$line['ERROR']." ".$line['JOB_NAME']." ".$line['END_TIME']." END TIME ".$line['ORDER_END_TIME']."<br/>"; 
                $Done[$job] = $line['END_TIME'];
                $type = substr($line['STATE'],0,1);
                if (($line['ERROR']>0) and ($type!='?')) {
                    // si c'est un job ordonné on verifie si l'état est suspendu
                    if ($line['CAUSE']=='order') {
                        if (($line['ORDER_END_TIME']=='') and ($line['STATE']==$line['ORDER_STATE'])) {
                            $Fatal_orders[$job]='SUSPEND ['.$line['SPOOLER_ID'].']'.$line['JOB_CHAIN'].','.$line['ORDER_ID'];
                        }
                        if ($line['STATE']=='!') {
                            $Fatal_orders[$job]='FATAL ['.$line['SPOOLER_ID'].']'.$line['JOB_CHAIN'].','.$line['ORDER_ID'];
                        }
                    }
                    else {
                       if (isset($Stopped[$job])) {
                           $Fatal_jobs[$job] = 1;
                       }
                    }
                    array_push($Errors,$job);
                }
            }
        }
        // Aucune erreur ?
        
        // Statut erreur ? 
        if (count($Fatal_orders)+count($Fatal_jobs)>0) {
            // CRITICAL
            $statut = 2;
        }
        else  {
            // WARNING
            $statut = 1;
        }
//        header("HTTP/1.0 1 Not Found");

        foreach ($Errors as $job) {
            print "$job: ".$Done[$job];
            if (isset($Fatal_orders[$job])) {
                print $Fatal_orders[$job];
            }
            if (isset($Fatal_jobs[$job])) {
                print " STOP ";
            }
            print "<br/>\n";
        }
        exit(3);

        $output->writeln($text);
    }
}