export interface LayoutProps {
    companyCode: string | null;
    companyDisplayName: string | null;
    departmentCode: string | null;
    role: string | null;
    hrAccess: boolean;
    hasSubordinates: boolean;
    shortName: string | null;
    fullName: string | null;
    employeeName: string | null;
    salutation: string | null;
    position: string | null;
    adminImpersonating: boolean;
    quarterControlAccess: boolean;
    unreadNotificationCount: number;
    themeAccent2: string;
    themeSidebarBg: string;
    themeSidebarAccent: string;
    themeSidebarText: string;
}

export interface FlashProps {
    error?: string | null;
    success?: string | null;
}

export interface SharedPageProps {
    layout: LayoutProps;
    flash: FlashProps;
    [key: string]: unknown;
}
