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
    themeBg: string;
    themeCard: string;
    themeBorder: string;
    themeAccent: string;
    themeAccent2: string;
    themeText: string;
    themeSidebarBg: string;
    themeSidebarAccent: string;
    themeSidebarText: string;
    logoUrl: string | null;
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
