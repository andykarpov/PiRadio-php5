<?php

class Process {

    public static $status = 0;

    /**
     * Call a subprogram redirecting the standard pipes.
     *
     * @param string $cmd The full command to execute
     * @param string $process_stdout Buffer to store STDOUT output of the process
     * @param mixed $process_stdin Input to feed to the process on STDIN
     * @param mixed $env env variables
     * @return mixed true on success, false on error
     * @throws Exception
     */
    public static function forkProcess($cmd, &$process_stdout, $process_stdin = false, $env = null) {
        $descriptorspec = array(
            0 => array("pipe", "r"), // stdin is a pipe that the child will read from
            1 => array("pipe", "w"), // stdout is a pipe that the child will write to
            2 => array("pipe", "w") // stderr is a pipe that the child will write to
        );

        $pipes = null;

        $proc = proc_open($cmd, $descriptorspec, $pipes, null, $env, array('binary_pipes' => true));

        if (is_resource($proc)) {

            $pipe_stdin = $pipes[0];
            $pipe_stdout = $pipes[1];

            stream_set_blocking($pipe_stdin, 0);
            stream_set_blocking($pipe_stdout, 0);

            while ($pipe_stdout || $pipe_stdin) {
                $select_pipes_r = array();
                if ($pipe_stdout) $select_pipes_r[] = $pipe_stdout;

                $select_pipes_w = array();
                if ($pipe_stdin) $select_pipes_w[] = $pipe_stdin;

                stream_select($select_pipes_r, $select_pipes_w, $dummy = null, 1, 0);

                // write?
                if (in_array($pipe_stdin, $select_pipes_w)) {
                    // slurp from $process_stdin into $buf
                    $buf = substr($process_stdin, 0, 8192);
                    $process_stdin = substr($process_stdin, 8192);

                    fwrite($pipe_stdin, $buf);

                    // done writing?
                    if (strlen($process_stdin) <= 0 || feof($pipe_stdin)) {
                        fclose($pipe_stdin);
                        $pipe_stdin = null;
                    }
                }

                // read?
                if (in_array($pipe_stdout, $select_pipes_r)) {
                    $process_stdout .= fread($pipe_stdout, 8192);

                    // done reading?
                    if (feof($pipe_stdout)) {
                        fclose($pipe_stdout);
                        $pipe_stdout = null;
                    }
                }
            }

            $status = proc_close($proc);
            self::$status = $status;
            return ($status == 0);

        } else {
            throw new Exception('Unable to fork the command');
        }
    }
}
