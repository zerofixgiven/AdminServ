<?php	// AUTHENTICATION	$LEVEL = 'User';	// ISSET	if( isset($_GET['cfg']) ){ $path_cfg = $_GET['cfg']; }else{ $path_cfg = null; }	if( isset($_GET['rsc']) ){ $path_rsc = $_GET['rsc']; }else{ $path_rsc = null; }	// INCLUDES    if(preg_match('/\.cfg\.php$/', $path_cfg)){        $serverConfig = '../../'.str_replace('../', '', $path_cfg);        if(file_exists($serverConfig)){            require_once $serverConfig;        }    }else{        header("HTTP/1.1 400 Bad Request");        die("Specified file is not a config.");    }	require_once '../class/GbxRemote.inc.php';	require_once '../class/tmnick.class.php';	require_once '../class/utils.class.php';	$langCode = Utils::getLang();	$langFile = '../lang/'.$langCode.'.php';	if( file_exists($langFile) ){		require_once $langFile;	}	// FUNCTIONS	function sortByTeam($a, $b){		// Modification		if($a['teamId'] == 0){			$a['teamId'] = 'blue';		}else if($a['teamId'] == 1){			$a['teamId'] = 'red';		}else{			$a['teamId'] = 'spectator';		}		if($b['teamId'] == 0){			$b['teamId'] = 'blue';		}else if($b['teamId'] == 1){			$b['teamId'] = 'red';		}else{			$b['teamId'] = 'spectator';		}		// Comparaison		if($a['teamId'] == $b['teamId']){			return 0;		}		if($a['teamId'] < $b['teamId']){			return -1;		}else{			return 1;		}	}	// DATA	$out = array();	if( class_exists('ServerConfig') ){		if( isset(ServerConfig::$SERVERS) && count(ServerConfig::$SERVERS) > 0 && !isset(ServerConfig::$SERVERS['new server name']) && !isset(ServerConfig::$SERVERS['']) ){			$serverId = 0;			foreach(ServerConfig::$SERVERS as $serverName => $serverValues){				// Connexion				$client = new IXR_ClientMulticall_Gbx;				if( !$client->InitWithIp($serverValues['address'], $serverValues['port']) ){					$out['servers'][$serverId]['error'] = Utils::t('Offline server.');				}				else{					if( !$client->query('Authenticate', $LEVEL, $serverValues['ds_pw']) ){						$out['servers'][$serverId]['error'] = Utils::t('Authentication error.');					}					else{						// Connecté sur						$client->query('GetVersion');						$version = $client->getResponse();						$out['servers'][$serverId]['version']['name'] = $version['Name'];						// Protocole : tmtp ou maniaplanet						$linkProtocol = 'maniaplanet';						if($version['Name'] == 'TmForever'){							$linkProtocol = 'tmtp';						}						$out['servers'][$serverId]['version']['protocol'] = $linkProtocol;						// Jeu						if($version['Name'] == 'TmForever'){							$queryName = array(								'getMapInfo' => 'GetCurrentChallengeInfo',							);						}						else{							$queryName = array(								'getMapInfo' => 'GetCurrentMapInfo',							);						}						// Requêtes						$client->addCall('GetServerName');						$client->addCall('GetSystemInfo');						$client->addCall('GetStatus');						$client->addCall('GetGameMode');						$client->addCall($queryName['getMapInfo']);						$client->addCall('GetPlayerList', array(50, 0) );						$client->addCall('GetMaxPlayers');						$client->multiquery();						$queriesData = $client->getMultiqueryResponse();						// Nom						$out['servers'][$serverId]['name'] = TmNick::toHtml($queriesData['GetServerName'], 10, true, false, '#999');						// Login						$system = $queriesData['GetSystemInfo'];						$out['servers'][$serverId]['serverlogin'] =  $system['ServerLogin'];						// Title						$title = null;						if($version['Name'] != 'TmForever'){							$title = $system['TitleId'];						}						$out['servers'][$serverId]['version']['title'] = $title;						// Statut						$status = $queriesData['GetStatus'];						$out['servers'][$serverId]['status'] = $status['Name'];						// GameMode						$gameMode = $queriesData['GetGameMode'];						$gameModeListName = array(							'Script',							'Rounds',							'TimeAttack',							'Team',							'Laps',							'Laps',							'Stunts',							'Cup'						);						if($version['Name'] == 'TmForever'){							$gameMode++;						}						$gameModeName = $gameModeListName[$gameMode];                        if($gameModeName == 'Script') {                            $client->addCall('GetScriptName');                            $client->multiquery();                            $scriptname = $client->getMultiqueryResponse();                            $out['servers'][$serverId]['gamemode'] = $gameModeName." (".str_replace('.Script.txt','',$scriptname['GetScriptName']['CurrentValue']).")";                        } else {						    $out['servers'][$serverId]['gamemode'] = $gameModeName;                        }						// Map						$currentMapInfo = $queriesData[$queryName['getMapInfo']];						$currentMapEnv = $currentMapInfo['Environnement'];						if($currentMapEnv == 'Speed'){							$currentMapEnv = 'Desert';						}						else if($currentMapEnv == 'Alpine'){							$currentMapEnv = 'Snow';						}						$currentMapEnvFile = null;						$pathToEnvFile = $path_rsc.'images/env/'.strtolower($currentMapEnv).'.png';						if( file_exists('../../'.$pathToEnvFile) ){							$currentMapEnvFile = $pathToEnvFile;						}						$out['servers'][$serverId]['map']['name'] = TmNick::toHtml(htmlspecialchars($currentMapInfo['Name'], ENT_QUOTES, 'UTF-8'), 10, true, false, '#999');						$out['servers'][$serverId]['map']['env']['name'] = $currentMapEnv;						$out['servers'][$serverId]['map']['env']['filename'] = $currentMapEnvFile;						// Players						$playerList = $queriesData['GetPlayerList'];						$countPlayerList = count($playerList);						if( $countPlayerList > 0 ){							$playerId = 0;							foreach($playerList as $player){								if($player['IsSpectator'] != 0){ $playerStatus = Utils::t('Spectator'); }else{ $playerStatus = Utils::t('Player'); }								if($player['TeamId'] == 0){ $teamName = Utils::t('Blue'); }else if($player['TeamId'] == 1){ $teamName = Utils::t('Red'); }else{ $teamName = Utils::t('Spectator'); }								$out['players'][$serverId]['list'][$playerId] = array(									'name' => TmNick::toHtml(htmlspecialchars($player['NickName'], ENT_QUOTES, 'UTF-8'), 10, true),									'status' => $playerStatus,									'teamId' => $player['TeamId'],									'teamName' => $teamName								);								$playerId++;							}						}						else{							$out['players'][$serverId]['list'] = Utils::t('No player');						}						// Tri mode team						if($gameModeName == 'Team' && $countPlayerList > 0){							uasort($out['players'][$serverId]['list'], 'sortByTeam');						}						// Count players						$maxPlayers = $queriesData['GetMaxPlayers'];						$out['players'][$serverId]['count']['current'] = $countPlayerList;						$out['players'][$serverId]['count']['max'] = $maxPlayers['NextValue'];					}				}				// Déconnexion				$client->Terminate();				$serverId++;			}		}	}	// Retour	echo json_encode($out);?>