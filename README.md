PHP EIP Driver

The Excel sheet describes the EIP messaging format and sequence of messages.
It should start with the following messages:

1.  Determine CIP Identity
2.  Determine Encapsulation Service Classes
3.  Register Session
4.  Connect To Message Router
5.  Read Tag

The latest version is plc.php.  Then we have a cron_plcread_task.php that uses the plc.php class to collect PLC tag data on a schedule.
Still needs work, but it does function...
