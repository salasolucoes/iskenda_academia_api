# Perguntas Para a IsKenda

> Documento de levantamento de requisitos e decisões de negócio.
> Cada secção corresponde a um domínio da plataforma. Marcar as opções com `[x]` e preencher os campos livres.

---

## 1. Alunos — Acesso e Requisitos

### 1.1 Requisitos para frequentar um curso

- [ ] Apenas cadastro + verificação de e-mail (actual)
- [ ] Cadastro + verificação de e-mail + verificação de identidade (documento oficial)
- [ ] Cadastro + verificação de e-mail + verificação de telefone (SMS/WhatsApp)
- [ ] Outro: `________________________`

### 1.2 Documentos requeridos do estudante

| Documento | Obrigatório? | Quando recolher? |
|-----------|-------------|-----------------|
| Bilhete de identidade | [ ] Sim [ ] Não | `________________________` |
| Passaporte | [ ] Sim [ ] Não | `________________________` |
| Comprovativo de residência | [ ] Sim [ ] Não | `________________________` |
| Foto do perfil | [ ] Sim [ ] Não | `________________________` |
| Outro: `____________` | [ ] Sim [ ] Não | `________________________` |

### 1.3 Estado da conta do estudante

- [ ] Conta activa imediatamente após cadastro (actual)
- [ ] Conta activa após aprovação manual do admin
- [ ] Conta activa após verificação de identidade aprovada
- [ ] Outro: `________________________`

---

## 2. Pagamento

### 2.1 Método de pagamento

- [ ] Comprovante de transferência/depósito bancário enviado pelo estudante → validado pelo admin (actual)
- [ ] Gateway de pagamento integrado — qual? `________________________`
  - [ ] Emis
  - [ ] Paystack
  - [ ] Flutterwave
  - [ ] Stripe
  - [ ] Outro: `________________________`
- [ ] Ambos (comprovante + gateway como alternativa)
- [ ] Outro: `________________________`

### 2.2 Se gateway de pagamento

- [ ] Pagamento único por curso
- [ ] Pagamento com planos de assinatura (mensal/anual)
- [ ] Pagamento parcelado
- [ ] Outro: `________________________`

### 2.3 Carteira (Wallet)

- [ ] Manter sistema actual de carteira pré-paga com vouchers
- [ ] Gateway de pagamento alimenta a carteira automaticamente
- [ ] Pagamento directo ao curso (sem carteira intermédia)
- [ ] Outro: `________________________`

### 2.4 Moeda

- [ ] Kzs (Kwanza angolano) — actual
- [ ] Multi-moeda
- [ ] Outro: `________________________`

---

## 3. Conteúdo — Vídeos

### 3.1 Formato dos vídeos

- [ ] Vídeos gravados e hospedados na plataforma (actual — MinIO/S3)
- [ ] Vídeos de plataformas externas (YouTube, Vimeo, etc.)
  - [ ] Apenas YouTube
  - [ ] Apenas Vimeo
  - [ ] YouTube + Vimeo
  - [ ] Outro: `________________________`
- [ ] Ambos (hospedados + externos)
- [ ] Outro: `________________________`

### 3.2 Upload de vídeos

- [ ] Instrutor faz upload directo para a plataforma (actual)
- [ ] Instrutor cola link de plataforma externa
- [ ] Admin faz upload em nome do instrutor
- [ ] Ambos (upload + link)
- [ ] Outro: `________________________`

### 3.3 Controlo de acesso aos vídeos

- [ ] Vídeo acessível apenas após pagamento/matrícula (actual)
- [ ] Vídeo acessível apenas com login
- [ ] Primeiro módulo/grátis sem pagamento
- [ ] Pré-visualização parcial (ex: 2 minutos grátis)
- [ ] Outro: `________________________`

### 3.4 Download de vídeos

- [ ] Sem download — streaming apenas (actual)
- [ ] Download permitido para estudo offline
- [ ] Outro: `________________________`

---

## 4. Conteúdo — Lives/Sessões ao Vivo

### 4.1 Plataforma de streaming

- [ ] Streaming nativo na plataforma via WebRTC/Coturn (actual)
- [ ] Integração com Zoom
- [ ] Integração com Google Meet
- [ ] Integração com Microsoft Teams
- [ ] Integração com outra plataforma: `________________________`
- [ ] Streaming nativo + opção de integração externa
- [ ] Outro: `________________________`

### 4.2 Quem pode iniciar a live

- [ ] Apenas o instrutor do curso
- [ ] Instrutor ou admin
- [ ] Apenas o admin
- [ ] Outro: `________________________`

### 4.3 Interacção na live

- [ ] Chat de texto (actual)
- [ ] Chat de texto + áudio
- [ ] Chat de texto + áudio + vídeo (bidireccional)
- [ ] Perguntas e respostas (Q&A)
- [ ] Enquetes/pesquisas ao vivo
- [ ] Compartilhar ecrã
- [ ] Outro: `________________________`

### 4.4 Gravação de lives

- [ ] Lives não são gravadas
- [ ] Lives são gravadas e ficam disponíveis para re-ver (actual)
- [ ] Lives são gravadas mas apenas o admin/instrutor pode aceder
- [ ] Outro: `________________________`

### 4.5 Acesso à live

- [ ] Apenas estudantes matriculados no curso
- [ ] Estudantes matriculados + convidados (link com token)
- [ ] Público aberto (qualquer pessoa com link)
- [ ] Outro: `________________________`

### 4.6 Interação total na plataforma

- [ ] Sim, o estudante deve fazer tudo dentro da plataforma (actual)
- [ ] Não, pode ser redirecionado para plataforma externa (Zoom/Meet)
- [ ] Depende do tipo de aula
- [ ] Outro: `________________________`

---

## 5. Conteúdo — Aulas Escritas/PDF

### 5.1 Formato do conteúdo

- [ ] PDFs uploadados pelo instrutor (actual)
- [ ] Editor de texto integrado (WYSIWYG)
- [ ] Markdown
- [ ] Outro: `________________________`

### 5.2 Download de materiais

- [ ] Download permitido (actual)
- [ ] Apenas visualização na plataforma
- [ ] Conforme política por curso
- [ ] Outro: `________________________`

---

## 6. Certificados

### 6.1 Emissão automática

- [ ] Emitir ao completar 100% das aulas
- [ ] Emitir ao completar X% das aulas (qual %?): `________`
- [ ] Emitir após avaliação/teste final
- [ ] Emitir manualmente pelo admin/instrutor
- [ ] Outro: `________________________`

### 6.2 Conteúdo do certificado

- [ ] Nome do estudante, nome do curso, data de conclusão, hash de verificação (actual)
- [ ] Adicionar carga horária total
- [ ] Adicionar nota/qualificação
- [ ] Adicionar assinatura digital do instrutor
- [ ] Adicionar logo da instituição
- [ ] Outro: `________________________`

### 6.3 Verificação pública

- [ ] Link público com hash SHA-256 (actual)
- [ ] QR Code no PDF que aponta para link de verificação
- [ ] Código alfanumérico manual
- [ ] Outro: `________________________`

---

## 7. Suporte/Atendimento

### 7.1 Canal de suporte

- [ ] Sistema de tickets dentro da plataforma (actual)
- [ ] E-mail de suporte
- [ ] WhatsApp
- [ ] Chat ao vivo (intercom/crisp/etc.)
- [ ] Telefone
- [ ] Todos os acima
- [ ] Outro: `________________________`

### 7.2 Priorização de tickets

- [ ] Sistema de prioridade: baixa/média/alta (actual)
- [ ] SLA definido (tempo de resposta máxima)
- [ ] Outro: `________________________`

### 7.3 Departamentos de suporte

- [ ] Suporte geral (actual)
- [ ] Separar: suporte técnico / suporte académico / facturas
- [ ] Outro: `________________________`

---

## 8. Notificações

### 8.1 Canais de notificação

- [ ] In-app (dentro da plataforma) (actual)
- [ ] E-mail
- [ ] SMS
- [ ] WhatsApp
- [ ] Push notifications (mobile)
- [ ] Outro: `________________________`

### 8.2 Tipos de notificação

| Evento | In-app | E-mail | SMS | WhatsApp | Push |
|--------|--------|--------|-----|----------|------|
| Novo curso disponível | [ ] | [ ] | [ ] | [ ] | [ ] |
| Aula ao vivo agendada | [ ] | [ ] | [ ] | [ ] | [ ] |
| Voucher aprovado/rejeitado | [ ] | [ ] | [ ] | [ ] | [ ] |
| Resposta a ticket | [ ] | [ ] | [ ] | [ ] | [ ] |
| Certificado emitido | [ ] | [ ] | [ ] | [ ] | [ ] |
| Compra de curso | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## 9. Administração

### 9.1 Acesso ao painel admin

- [ ] Painel web integrado (actual — API only, sem frontend)
- [ ] Painel web separado (SPA dedicado)
- [ ] Outro: `________________________`

### 9.2 Utilizadores administradores

- Quantos adminstradores previstos? `________`
- [ ] Super admin + admin com permissões diferentes
- [ ] Todos os admin têm as mesmas permissões
- [ ] Outro: `________________________`

### 9.3 Dashboard do admin

- [ ] Métricas gerais (cursos, alunos, receitas) (actual)
- [ ] Relatórios exportáveis (PDF/Excel)
- [ ] Gráficos de evolução temporal
- [ ] Outro: `________________________`

---

## 10. Instrutores

### 10.1 Criação de conta de instrutor

- [ ] Admin cria e envia OTP temporário (actual)
- [ ] Pedido de registo pelo instrutor → aprovação do admin
- [ ] Convite por e-mail pelo admin
- [ ] Outro: `________________________`

### 10.2 Permissões do instrutor

- [ ] Criar/editar/apagar os seus próprios cursos (actual)
- [ ] Apenas criar cursos (admin publica)
- [ ] Ver lista de alunos matriculados nos seus cursos (actual)
- [ ] Ver dashboard com estatísticas (actual)
- [ ] Gerir sessões ao vivo (actual)
- [ ] Outro: `________________________`

### 10.3 Remuneração do instrutor

- [ ] Não aplicável (instrutor é funcionário)
- [ ] Percentagem por venda
- [ ] Fixo mensal
- [ ] Outro: `________________________`

---

## 11. Cursos

### 11.1 Estrutura do curso

- [ ] Curso → Módulos → Aulas (actual)
- [ ] Curso → Módulos → Sub-módulos → Aulas
- [ ] Outro: `________________________`

### 11.2 Tipos de aula

- [ ] Vídeo (actual)
- [ ] PDF/Documento (actual)
- [ ] Live/Sessão ao vivo (actual)
- [ ] Quiz/Teste
- [ ] Exercício prático
- [ ] Fórum de discussão
- [ ] Outro: `________________________`

### 11.3 Estados do curso

- [ ] Rascunho → Publicado → Arquivado (actual)
- [ ] Adicionar estado "Em revisão"
- [ ] Adicionar estado "Suspenso"
- [ ] Outro: `________________________`

### 11.4 Categorização

- [ ] Uma categoria por curso (actual)
- [ ] Múltiplas categorias por curso
- [ ] Tags adicionais
- [ ] Outro: `________________________`

### 11.5 Pré-requisitos

- [ ] Sem pré-requisitos (actual)
- [ ] Curso X obrigatório antes de aceder ao curso Y
- [ ] Outro: `________________________`

---

## 12. Matrícula e Progresso

### 12.1 Fluxo de matrícula

- [ ] Carrinho → Checkout → Pagamento → Matrícula (actual)
- [ ] Compra directa (sem carrinho)
- [ ] Matrícula gratuita (sem pagamento)
- [ ] Outro: `________________________`

### 12.2 Progresso do aluno

- [ ] Marcar aula como concluída manualmente (actual)
- [ ] Progresso automático por tempo assistido (actual — watched_seconds)
- [ ] Progresso por conclusão de quiz/teste
- [ ] Combinação dos acima
- [ ] Outro: `________________________`

### 12.3 Conclusão do curso

- [ ] 100% das aulas concluídas (actual — 90% threshold)
- [ ] X% das aulas + teste final aprovado
- [ ] X% das aulas + avaliação do instrutor
- [ ] Outro: `________________________`

### 12.4 Acesso após conclusão

- [ ] Acesso vitalício ao conteúdo (actual)
- [ ] Acesso por tempo limitado (X meses): `________`
- [ ] Acesso condicional a renovação
- [ ] Outro: `________________________`

---

## 13. Integrações Externas

### 13.1 Pagamento

- [ ] Gateway de pagamento (ver secção 2)
- [ ] Integração bancária directa
- [ ] Outro: `________________________`

### 13.2 Comunicação

- [ ] E-mail transaccional (actual — SMTP)
- [ ] WhatsApp Business API
- [ ] Telegram Bot
- [ ] Outro: `________________________`

### 13.3 Conteúdo

- [ ] YouTube API
- [ ] Vimeo API
- [ ] Google Drive
- [ ] Outro: `________________________`

### 13.4 Analytics

- [ ] Google Analytics
- [ ] Meta Pixel
- [ ] Interno apenas (actual)
- [ ] Outro: `________________________`

---

## 14. Escalabilidade e Infraestrutura

### 14.1 Utilizadores previstos (fase 1)

- Estudantes: `________`
- Instrutores: `________`
- Cursos: `________`
- Admins: `________`

### 14.2 Utilizadores previstos (fase 2 — 1 ano)

- Estudantes: `________`
- Instrutores: `________`
- Cursos: `________`

### 13.3 Idiomas

- [ ] Português (pt-BR) — actual
- [ ] Português (pt-AO)
- [ ] Inglês
- [ ] Francês
- [ ] Multi-idioma (tradução de interface)
- [ ] Outro: `________________________`

---

## 15. Monetização

### 15.1 Modelo de receita

- [ ] Venda avulsa por curso (actual)
- [ ] Assinatura mensal/anual (acesso a todos os cursos)
- [ ] Modelo freemium (cursos grátis + premium)
- [ ] Corporativo (empresas compram acesso para colaboradores)
- [ ] Combinação dos acima
- [ ] Outro: `________________________`

### 15.2 Preços

- Faixa de preço por curso: `________` Kzs
- Descontos/promoções: [ ] Sim [ ] Não
- Cupões de desconto: [ ] Sim [ ] Não

---

## 16. Questões Técnicas Pendentes

> Decisões que impactam directamente a arquitectura do sistema.

| # | Questión | Opção A | Opção B | Decidido? |
|---|----------|---------|---------|-----------|
| 1 | Pagamento via gateway ou comprovante? | Comprovante (actual) | Gateway Emis | [ ] |
| 2 | Vídeos hospedados ou YouTube? | Hospedados (actual) | YouTube | [ ] |
| 3 | Live nativa ou Zoom/Meet? | Nativa WebRTC (actual) | Zoom/Meet | [ ] |
| 4 | Carrinho obrigatório? | Sim (actual) | Compra directa | [ ] |
| 5 | Carteira pré-paga ou pagamento directo? | Carteira (actual) | Directo | [ ] |
| 6 | Certificado automático ou manual? | Automático (actual) | Manual | [ ] |
| 7 | Multi-idioma? | Não (actual) | Sim | [ ] |
| 8 | App mobile? | Não (actual) | Sim | [ ] |

---

## 17. Próximos Passos

- [ ] Reunião com o dono para validar respostas
- [ ] Priorizar funcionalidades (MVP vs fase 2)
- [ ] Actualizar regras de negócio em `docs/regras-de-negocio.md`
- [ ] Criar plano de implementação
- [ ] Estimar prazos e custos

---

*Documento gerado em: `____/____/________`*
*Respondido por: `________________________`*
*Revisado por: `________________________`*
