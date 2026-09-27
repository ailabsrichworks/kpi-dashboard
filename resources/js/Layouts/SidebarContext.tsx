import { createContext, useContext, useEffect, useState, ReactNode } from 'react';

interface SidebarContextValue {
    collapsed: boolean;
    setCollapsed: (collapsed: boolean) => void;
    toggle: () => void;
}

const SidebarContext = createContext<SidebarContextValue | null>(null);

const STORAGE_KEY = 'sidebarCollapsed';

export function SidebarProvider({ children }: { children: ReactNode }) {
    const [collapsed, setCollapsedState] = useState(false);

    useEffect(() => {
        setCollapsedState(localStorage.getItem(STORAGE_KEY) === 'true');
    }, []);

    const setCollapsed = (value: boolean) => {
        setCollapsedState(value);
        localStorage.setItem(STORAGE_KEY, value ? 'true' : 'false');
    };

    return (
        <SidebarContext.Provider value={{ collapsed, setCollapsed, toggle: () => setCollapsed(!collapsed) }}>
            {children}
        </SidebarContext.Provider>
    );
}

export function useSidebar(): SidebarContextValue {
    const ctx = useContext(SidebarContext);
    if (!ctx) throw new Error('useSidebar must be used within a SidebarProvider');
    return ctx;
}
