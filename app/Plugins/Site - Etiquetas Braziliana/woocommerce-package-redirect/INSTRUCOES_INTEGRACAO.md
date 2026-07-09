# Integração Sistema Braziliana ↔ WordPress Etiquetas

## Visão Geral

O sistema Braziliana (brazilianashop.com.br) agora faz requisições para o WordPress Etiquetas (etiquetas.brazilianashop.com.br) para criar etiquetas, containers, faturas e embarques.

O WordPress é o responsável por:
- Autenticar na API dos Correios PACKET
- Criar os pacotes/containers/faturas/embarques
- Manter o registro de tudo
- Gerar os PDFs

## Configuração

### 1. No WordPress (etiquetas.brazilianashop.com.br)

1. Vá em **Snippets** no menu lateral
2. Clique em **Adicionar Novo**
3. Cole todo o conteúdo do arquivo `snippet-api-etiquetas.php`
4. **IMPORTANTE**: Altere a constante `BRZ_API_KEY` para uma chave segura única
5. Ative o snippet

### 2. No Sistema (brazilianashop.com.br)

Insira as seguintes configurações na tabela `configuracoes_sistema`:

```sql
INSERT INTO configuracoes_sistema (chave, valor) VALUES 
('wp_etiquetas_url', 'https://etiquetas.brazilianashop.com.br'),
('wp_etiquetas_api_key', 'MESMA_CHAVE_QUE_VOCE_COLOCOU_NO_SNIPPET')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
```

## Endpoints Disponíveis

Base URL: `https://etiquetas.brazilianashop.com.br/wp-json/brz/v1/`

### Autenticação
Todas as requisições precisam do header: `X-API-Key: SUA_CHAVE`

### Pacotes (Etiquetas)
- `POST /packages/create` - Criar etiqueta
- `GET /packages` - Listar pacotes
- `GET /packages?without_container=1` - Pacotes sem container

### Containers (Unitizadores)
- `POST /containers/create` - Criar container
- `GET /containers` - Listar containers
- `GET /containers?without_bill=1` - Containers sem fatura

### Faturas (CN38)
- `POST /bills/create` - Criar fatura
- `GET /bills` - Listar faturas
- `GET /bills?without_departure=1` - Faturas sem embarque

### Embarques
- `POST /departures/create` - Confirmar embarque
- `GET /departures` - Listar embarques

### Saldo
- `GET /balance` - Consultar saldo de códigos de rastreio

## Fluxo de Uso

1. **Gerar Etiquetas** → `POST /packages/create` (uma por pedido)
2. **Criar Container** → `POST /containers/create` (com os tracking codes gerados)
3. **Criar Fatura** → `POST /bills/create` (com os container IDs do WordPress)
4. **Confirmar Embarque** → `POST /departures/create` (com os bill IDs)

## Exemplo: Criar Etiqueta

```json
POST /wp-json/brz/v1/packages/create
Headers: X-API-Key: sua_chave

{
    "customerControlCode": "PED-000123",
    "totalWeight": 500,
    "packagingLength": 20,
    "packagingWidth": 15,
    "packagingHeight": 10,
    "recipientName": "João Silva",
    "recipientDocumentType": "CPF",
    "recipientDocumentNumber": "12345678900",
    "recipientAddress": "Rua Exemplo",
    "recipientAddressNumber": "123",
    "recipientAddressComplement": "Apto 1",
    "recipientCityName": "São Paulo",
    "recipientState": "SP",
    "recipientZipCode": "01001000",
    "recipientEmail": "joao@email.com",
    "recipientPhoneNumber": "11999998888",
    "freightPaidValue": 0.01,
    "distributionModality": 33162,
    "taxPaymentMethod": "DDU",
    "currency": "USD",
    "items": [
        {
            "hsCode": "61091000",
            "description": "Camiseta",
            "quantity": 2,
            "value": 15.00
        }
    ]
}
```

Resposta:
```json
{
    "success": true,
    "tracking_number": "NX123456789BR",
    "wp_post_id": 456
}
```

## Exemplo: Criar Container

```json
POST /wp-json/brz/v1/containers/create
{
    "dispatchNumber": 1,
    "trackingCodes": ["NX123456789BR", "NX987654321BR"],
    "originCountry": "US",
    "originOperatorName": "USPS",
    "destinationOperatorName": "CWBA",
    "postalCategoryCode": "A",
    "serviceSubclassCode": "NX",
    "unitType": "2",
    "triageGroup": "1"
}
```

## Exemplo: Criar Fatura

```json
POST /wp-json/brz/v1/bills/create
{
    "containerIds": [456, 457]
}
```

## Exemplo: Criar Embarque

```json
POST /wp-json/brz/v1/departures/create
{
    "billIds": [789],
    "flightNumber": 1234,
    "airlineCode": "LA",
    "departureDate": "2024-12-20T10:00:00Z",
    "departureAirportCode": "MIA",
    "arrivalDate": "2024-12-21T06:00:00Z",
    "arrivalAirportCode": "GRU"
}
```
