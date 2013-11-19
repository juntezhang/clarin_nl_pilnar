#@------------------------------------------------------------------------------------------------------------------------------------------
#@ Copyright 2013 by Junte Zhang <juntezhang@gmail.com>
#@ Distributed under the GNU General Public Licence
#@
#@ README for CMDI MI indexing procedure of PILNAR, extended with the SolrCell module for indexing legacy data
#@------------------------------------------------------------------------------------------------------------------------------------------

The PHP class IndexCMDI does everything.

- indexpilnar.php is the script that uses the IndexCMDI class and manages the indexing of each CMDI file
- searchpilnar.php is the proxy that allows for the querying of the PILNAR index.

- data has 2 example CMDI files. It works on these 2 files, but I can give a limited guarantee it works on other CMDI files as well. ;-)