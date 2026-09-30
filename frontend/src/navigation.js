/**
 * Menu lateral. `roles` define quem vê o item.
 */
export const navigation = [
  {
    label: 'Geral',
    items: [
      { label: 'Painel', icon: 'pi pi-home', to: { name: 'dashboard' }, roles: ['admin', 'professor'] },
      { label: 'Meu espaço', icon: 'pi pi-star', to: { name: 'portal' }, roles: ['atleta', 'responsavel'] }
    ]
  },
  {
    label: 'Atletas',
    items: [
      { label: 'Atletas', icon: 'pi pi-users', to: { name: 'athletes' }, roles: ['admin', 'professor'] },
      { label: 'Responsáveis', icon: 'pi pi-id-card', to: { name: 'guardians' }, roles: ['admin'] }
    ]
  },
  {
    label: 'Treinos',
    items: [
      { label: 'Turmas', icon: 'pi pi-calendar', to: { name: 'classes' }, roles: ['admin', 'professor'] },
      { label: 'Chamada', icon: 'pi pi-check-square', to: { name: 'attendance' }, roles: ['admin', 'professor'] }
    ]
  },
  {
    label: 'Financeiro',
    items: [
      { label: 'Mensalidades', icon: 'pi pi-wallet', to: { name: 'invoices' }, roles: ['admin'] },
      { label: 'Inadimplência', icon: 'pi pi-exclamation-triangle', to: { name: 'delinquency' }, roles: ['admin'] },
      { label: 'Planos', icon: 'pi pi-tags', to: { name: 'plans' }, roles: ['admin'] },
      { label: 'Uniformes', icon: 'pi pi-shopping-bag', to: { name: 'uniforms' }, roles: ['admin'] },
      { label: 'Patrocínios', icon: 'pi pi-star', to: { name: 'sponsors' }, roles: ['admin'] }
    ]
  },
  {
    label: 'Administração',
    items: [
      { label: 'Usuários', icon: 'pi pi-user-edit', to: { name: 'users' }, roles: ['admin'] },
      { label: 'Auditoria', icon: 'pi pi-history', to: { name: 'audit' }, roles: ['admin'] }
    ]
  }
]
