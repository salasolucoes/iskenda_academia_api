# Questionário MVP — IsKenda Academy

> Perguntas críticas para definir o que entra no lançamento inicial (MVP).
> O sistema já tem: auth, cursos, carteira, vouchers, matrículas, certificados, tickets, notificações.
> Marcar com `[x]` e preencher campos livres.

---

## 1. Pagamento — O que usar no MVP?

### 1.1 Métodos de pagamento

- [x] Apenas comprovante de transferência/depósito (actual)
- [ ] Adicionar gateway de pagamento no MVP
  - Gateway: `________________________`

### 1.2 Se gateway, qual?

- [ ] Emis
- [ ] Paystack
- [ ] Flutterwave
- [ ] Stripe
- [ ] Outro: `________________________`

### 1.3 Moeda

- [x] Kzs (Kwanza angolano) — actual
- [ ] Multi-moeda

---

## 2. Vídeos — Como entregar o conteúdo?

### 2.1 Onde hospedar os vídeos?

- [x] Plataforma hospeda (MinIO/S3 — actual)
- [ ] YouTube (link externo)
- [ ] Vimeo (link externo)
- [ ] Ambos (hospedados + YouTube/Vimeo)

### 2.2 Streaming no MVP

- [x] Vídeo directo do MinIO/S3 (actual — sem transcodificação)
- [ ] Transcodificação HLS (necessita pipeline adicional)

### 2.3 Preview gratuito

- [x] Sem preview — só após pagamento (actual)
- [ ] Primeira aula grátis para todos
- [ ] Pré-visualização de 2 minutos

---

## 3. Aulas ao Vivo — Implementar no MVP?

### 3.1 Live no MVP?

- [ ] Sim, live é essencial para o MVP
- [ ] Não, usar apenas vídeos gravados
- [ ] Depois do MVP (fase 2)

### 3.2 Se live sim, que plataforma?

- [x] Redirect para Zoom/Meet (actual — sem WebRTC nativo)
- [ ] WebRTC nativo (requer mais desenvolvimento)
- [ ] Outro: `________________________`

### 3.3 Quem pode criar lives?

- [x] Admin cadastra link (actual)
- [ ] Instrutor pode criar a sua própria live

---

## 4. Matrícula e Acesso

### 4.1 Fluxo de compra

- [x] Carrinho → Checkout → Pagamento → Matrícula (actual)
- [ ] Compra directa (sem carrinho)

### 4.2 Acesso após conclusão

- [x] Acesso vitalício (actual)
- [ ] Acesso por tempo limitado: `________` meses

### 4.3 Pré-requisitos entre cursos

- [x] Sem pré-requisitos (actual)
- [ ] Curso X obrigatório antes do curso Y

---

## 5. Certificados

### 5.1 Emissão

- [x] Automático ao completar 100% (actual — threshold 90%)
- [ ] Automático ao completar X%: `________`%
- [ ] Manual pelo admin

### 5.2 Conteúdo do certificado

- [x] Nome + curso + data + hash de verificação (actual)
- [ ] Adicionar carga horária
- [ ] Adicionar logo da instituição

### 5.3 Verificação

- [x] Link público com hash SHA-256 (actual)
- [ ] QR Code no PDF

---

## 6. Suporte — Canais no MVP

### 6.1 Canal principal

- [x] Tickets dentro da plataforma (actual)
- [ ] E-mail de suporte
- [ ] WhatsApp
- [ ] Chat ao vivo

### 6.2 Departamentos

- [x] Suporte geral (actual)
- [ ] Separar: técnico / académico / facturas

---

## 7. Notificações — Canais no MVP

### 7.1 Canais activos

- [x] In-app + WebSocket (actual)
- [ ] E-mail transaccional
- [ ] SMS
- [ ] WhatsApp

### 7.2 Tipos de notificação

| Evento | In-app | E-mail | SMS |
|--------|--------|--------|-----|
| Voucher aprovado/rejeitado | [x] | [ ] | [ ] |
| Resposta a ticket | [x] | [ ] | [ ] |
| Certificado emitido | [x] | [ ] | [ ] |
| Aula ao vivo agendada | [x] | [ ] | [ ] |

---

## 8. Administração

### 8.1 Painel admin

- [x] API only — frontend separado (actual)
- [ ] Painel integrado na mesma app

### 8.2 Número de admins

- Quantos administradores no MVP? `________`
- [x] Super admin + admin com permissões diferentes (actual)
- [ ] Todos iguais

---

## 9. Instrutores

### 9.1 Criação de conta

- [x] Admin cria e envia OTP (actual)
- [ ] Pedido de registo → aprovação do admin

### 9.2 Conteúdo

- [x] Upload de vídeos para plataforma (actual)
- [ ] Link de YouTube/Vimeo
- [ ] Ambos

### 9.3 Remuneração

- [x] Funcionário — sem repartição (actual)
- [ ] Percentagem por venda: `________`%

---

## 10. Cursos

### 10.1 Estrutura

- [x] Curso → Módulos → Aulas (actual)
- [ ] Curso → Módulos → Sub-módulos → Aulas

### 10.2 Tipos de aula no MVP

- [x] Vídeo gravado (actual)
- [x] PDF/Documento (actual)
- [x] Live/Sessão ao vivo (actual)
- [ ] Quiz/Teste
- [ ] Exercício prático

### 10.3 Categorias

- [x] Uma categoria por curso (actual)
- [ ] Múltiplas categorias

---

## 11. Monetização

### 11.1 Modelo no MVP

- [x] Venda avulsa por curso (actual)
- [ ] Assinatura mensal/anual
- [ ] Freemium (grátis + premium)

### 11.2 Preços

- Faixa de preço por curso: `________` Kzs
- Descontos/promoções no MVP? [ ] Sim [x] Não
- Cupões no MVP? [ ] Sim [x] Não

---

## 12. Questões Técnicas Pendentes para o MVP

| # | Questão | Opção A | Opção B | Decidido? |
|---|---------|---------|---------|-----------|
| 1 | Pagamento via gateway? | Comprovante (actual) | Gateway | [ ] |
| 2 | Vídeos hospedados ou YouTube? | Hospedados (actual) | YouTube | [ ] |
| 3 | Live no MVP? | Redirect Zoom/Meet (actual) | WebRTC nativo | [ ] |
| 4 | Carrinho obrigatório? | Sim (actual) | Compra directa | [ ] |
| 5 | Transcodificação de vídeo? | Não (actual) | HLS | [ ] |
| 6 | Multi-idioma? | Não (actual) | Sim | [ ] |
| 7 | App mobile? | Não (actual) | Sim | [ ] |
| 8 | Pagamento parcelado? | Não (actual) | Sim | [ ] |

---

## 13. Escalabilidade — Números do MVP

### 13.1 Utilizadores previstos (lançamento)

- Estudantes: `________`
- Instrutores: `________`
- Cursos: `________`
- Admins: `________`

### 13.2 Idiomas

- [x] Português (pt-AO) — actual
- [ ] Inglês
- [ ] Multi-idioma

---

## 14. O que NÃO entra no MVP

> Confirmar funcionalidades adiadas para fase 2.

- [ ] WebRTC live streaming nativo
- [ ] Gateway de pagamento integrado
- [ ] Transcodificação de vídeo (HLS)
- [ ] App mobile
- [ ] Multi-idioma
- [ ] Quizzes/testes interactivos
- [ ] Download de vídeos offline
- [ ] Assinatura recorrente
- [ ] Cupões de desconto
- [ ] Relatórios exportáveis (PDF/Excel)
- [ ] Integração WhatsApp
- [ ] Integração Google Analytics
- [ ] Chat ao vivo (intercom/crisp)

---

*Documento gerado em: `____/____/________`*
*Respondido por: `________________________`*
