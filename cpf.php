<?php
header('Content-Type: text/plain; charset=utf-8');
require 'get_bearer.php';
$config = file_get_contents('config.json');
$config = json_decode($config, True);

if (!isset($_GET['consulta'])) {
    exit;
}

$cpf = trim($_GET['consulta']);
$cpf = str_replace([" ", "-", "_", ".", ","], "", $cpf);

if ($cpf == NULL) {
    die("⚠️ Por favor, digite um CPF.");
}

if (strlen($cpf) != 11) {
    die('⚠️ Por favor, digite um CPF válido.');
}

$bearer_token = $config['sipni_token'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://servicos-cloud.saude.gov.br/pni-bff/v1/cidadao/cpf/'.$cpf);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_ENCODING, "gzip");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'User-Agent: Mozilla/5.0 (Windows NT '.rand(11, 99).'.0; Win64; x64) AppleWebKit/'.rand(111, 991).'.'.rand(11, 99).' (KHTML, like Gecko) Chrome/'.rand(11, 99).'.0.0.0 Safari/537.36',
    'Authorization: Bearer '.$bearer_token,
    'DNT: 1',
    'Referer: https://si-pni.saude.gov.br/',
));
$re = curl_exec($ch);
$parsed = json_decode($re, True);

if (stripos($re, 'Token do usuário do SCPA inválido/expirado') || stripos($re, 'Não autorizado') || stripos($re, 'Unauthorized')) {
    $config['sipni_token'] = get_bearer_sipni();
    $config = json_encode($config);
    file_put_contents('config.json', $config);
    header('Location: ' . $_SERVER['PHP_SELF'].'?consulta='.$cpf);
}

if ($parsed['records'] == []) {
    die('⚠️ CPF não encontrado.');
}

if ($parsed['code'] == 200) {
    echo "👤 Dados Pessoais\n\n";
    
    if (isset($parsed['records'][0]['cpf'])) {
        echo '⤬ CPF: '.$parsed['records'][0]['cpf']."\n";
    }
    
    if (isset($parsed['records'][0]['cnsDefinitivo'])) {
        echo '⤬ CNS: '.$parsed['records'][0]['cnsDefinitivo']."\n\n";
    }
    
    if (isset($parsed['records'][0]['nome'])) {
        echo '⤬ Nome: '.$parsed['records'][0]['nome']."\n";
    }
    
    // Verificar se a data de nascimento está presente
    if (isset($parsed['records'][0]['dataNascimento'])) {
        $nascimento = explode('-', $parsed['records'][0]['dataNascimento']);
        echo '⤬ Nascimento: '.$nascimento[2].'/'.$nascimento[1].'/'.$nascimento[0]."\n";
        $idade = date('Y') - $nascimento[0];
        echo '⤬ Idade: '.$idade."\n";
    }
    
    if (isset($parsed['records'][0]['sexo'])) {
        $sexo = ($parsed['records'][0]['sexo'] == 'M') ? 'Masculino' : 'Feminino';
        echo '⤬ Gênero: '.$sexo."\n";
    }
    
    if (isset($parsed['records'][0]['grauQualidade'])) {
        echo '⤬ Grau de Qualidade: '.$parsed['records'][0]['grauQualidade']."\n";
    }

    // Município e Estado de Nascimento
    if (isset($parsed['records'][0]['nacionalidade']['municipioNascimento'])) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://servicos-cloud.saude.gov.br/pni-bff/v1/municipio/'.$parsed['records'][0]['nacionalidade']['municipioNascimento']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $re_tres = curl_exec($ch);
        $parsed_tres = json_decode($re_tres, True);

        if (isset($parsed_tres['record']['nome'])) {
            echo '⤬ Município Nascimento: '.$parsed_tres['record']['nome']."\n";
        }
        if (isset($parsed_tres['record']['siglaUf'])) {
            echo '⤬ Estado Nascimento: '.$parsed_tres['record']['siglaUf']."\n\n";
        }
    }

    // Óbito e Raça/Cor
    if (isset($parsed['records'][0]['obito'])) {
        $obito = ($parsed['records'][0]['obito'] == True) ? "Sim" : "Não";
        echo '⤬ Óbito: '.$obito."\n";
    }

    if (isset($parsed['records'][0]['racaCor'])) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://servicos-cloud.saude.gov.br/pni-bff/v1/racacor/'.$parsed['records'][0]['racaCor']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $re_segundo = curl_exec($ch);
        $parsed_segundo = json_decode($re_segundo, True);

        if (isset($parsed_segundo['record']['descricao'])) {
            echo '⤬ Cor: '.$parsed_segundo['record']['descricao']."\n\n";
        }
    }

    // Mãe e Pai
    if (isset($parsed['records'][0]['nomeMae'])) {
        echo '⤬ Mãe: '.$parsed['records'][0]['nomeMae']."\n";
    }
    
    if (isset($parsed['records'][0]['nomePai'])) {
        echo '⤬ Pai: '.$parsed['records'][0]['nomePai']."\n\n";
    }

    // Telefones
    if (isset($parsed['records'][0]['telefone']) && !empty($parsed['records'][0]['telefone'])) {
        echo "📞 Telefones\n\n";
        foreach ($parsed['records'][0]['telefone'] as $telefone) {
            echo $telefone['ddd'].$telefone['numero']."\n";
        }
    }

    // Endereço
    if (isset($parsed['records'][0]['endereco'])) {
        echo "\n🏠 Endereço\n\n";

        if (isset($parsed['records'][0]['endereco']['logradouro'])) {
            echo '⤬ Logradouro: '.$parsed['records'][0]['endereco']['logradouro']."\n";
        }

        if (isset($parsed['records'][0]['endereco']['numero'])) {
            echo '⤬ Número: '.$parsed['records'][0]['endereco']['numero']."\n";
        }

        if (isset($parsed['records'][0]['endereco']['bairro'])) {
            echo '⤬ Bairro: '.$parsed['records'][0]['endereco']['bairro']."\n";
        }

        if (isset($parsed['records'][0]['endereco']['municipio'])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://servicos-cloud.saude.gov.br/pni-bff/v1/municipio/'.$parsed['records'][0]['endereco']['municipio']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $re_quatro = curl_exec($ch);
            $parsed_quatro = json_decode($re_quatro, True);

            if (isset($parsed_quatro['record']['nome'])) {
                echo '⤬ Município: '.$parsed_quatro['record']['nome']."\n";
            }
        }

        if (isset($parsed['records'][0]['endereco']['siglaUf'])) {
            echo '⤬ Estado: '.$parsed['records'][0]['endereco']['siglaUf']."\n";
        }

        if (isset($parsed['records'][0]['endereco']['cep'])) {
            echo '⤬ Cep: '.$parsed['records'][0]['endereco']['cep']."\n\n";
        }
    }

    // Vacinas
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://servicos-cloud.saude.gov.br/pni-bff/v1/calendario/cpf/'.$cpf);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $re_cinco = curl_exec($ch);
    $parsed_cinco = json_decode($re_cinco, True);

    if (isset($parsed_cinco['record']['imunizacoesCampanha']['imunobiologicos']) && !empty($parsed_cinco['record']['imunizacoesCampanha']['imunobiologicos'])) {
        echo "💉 Vacinas:\n\n";
        foreach ($parsed_cinco['record']['imunizacoesCampanha']['imunobiologicos'] as $imunobiologico) {
            foreach ($imunobiologico['imunizacoes'] as $imunizacao) {
                echo "---- Tipo: ".$imunizacao['esquemaDose']['tipoDoseDto']['descricao']."\n";
                echo "⤬ Vacina: ".$imunobiologico['sigla']."\n";
                echo "⤬ Lote: ".$imunizacao['lote']."\n";
                echo "⤬ Data de Aplicacao: ".$imunizacao['dataAplicacao']."\n\n";
            }
        }
    }
}
?>