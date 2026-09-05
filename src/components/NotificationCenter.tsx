import React, { useState } from 'react';
import {
  Bell,
  X,
  CheckCircle2,
  AlertTriangle,
  Flame,
  Activity,
  CreditCard,
  Send,
  Radio,
  Clock,
  Trash2,
  CheckCheck,
  Filter,
  ExternalLink,
  ShieldAlert,
  Search,
  Sparkles,
  Volume2,
  VolumeX
} from 'lucide-react';
import { AppNotification, ActiveTab, NotificationCategory, Appointment } from '../types';

interface NotificationCenterProps {
  notifications: AppNotification[];
  isOpen: boolean;
  onClose: () => void;
  onMarkAsRead: (id: string) => void;
  onMarkAllAsRead: () => void;
  onClearAll: () => void;
  onDeleteNotification: (id: string) => void;
  onNavigateToTab: (tab: ActiveTab, appointmentId?: string) => void;
  onSelectAppointment?: (apt: Appointment) => void;
  appointments?: Appointment[];
  onTriggerTestAlert?: () => void;
}

export const NotificationCenter: React.FC<NotificationCenterProps> = ({
  notifications,
  isOpen,
  onClose,
  onMarkAsRead,
  onMarkAllAsRead,
  onClearAll,
  onDeleteNotification,
  onNavigateToTab,
  onSelectAppointment,
  appointments = [],
  onTriggerTestAlert,
}) => {
  const [activeCategory, setActiveCategory] = useState<'all' | NotificationCategory>('all');
  const [onlyUnread, setOnlyUnread] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [soundEnabled, setSoundEnabled] = useState(true);

  if (!isOpen) return null;

  const unreadCount = notifications.filter(n => !n.isRead).length;
  const criticalCount = notifications.filter(n => !n.isRead && (n.priority === 'critical' || n.category === 'stat')).length;

  const filteredNotifications = notifications.filter(n => {
    if (activeCategory !== 'all' && n.category !== activeCategory) return false;
    if (onlyUnread && n.isRead) return false;
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchTitle = n.title.toLowerCase().includes(q);
      const matchMsg = n.message.toLowerCase().includes(q);
      const matchPatient = n.patientName?.toLowerCase().includes(q);
      const matchToken = n.tokenNumber?.toLowerCase().includes(q);
      if (!matchTitle && !matchMsg && !matchPatient && !matchToken) return false;
    }
    return true;
  });

  const getCategoryIcon = (category: NotificationCategory, priority: string) => {
    if (priority === 'critical' || category === 'stat') {
      return <Flame className="w-4 h-4 text-rose-600 animate-pulse" />;
    }
    switch (category) {
      case 'workflow':
        return <Activity className="w-4 h-4 text-cyan-600" />;
      case 'billing':
        return <CreditCard className="w-4 h-4 text-emerald-600" />;
      case 'dispatch':
        return <Send className="w-4 h-4 text-blue-600" />;
      case 'pacs':
        return <Radio className="w-4 h-4 text-purple-600" />;
      case 'security':
        return <ShieldAlert className="w-4 h-4 text-amber-600" />;
      default:
        return <Bell className="w-4 h-4 text-slate-600" />;
    }
  };

  const getPriorityBadge = (priority: string) => {
    switch (priority) {
      case 'critical':
        return <span className="bg-rose-100 text-rose-700 text-[10px] font-black px-1.5 py-0.5 rounded border border-rose-200 uppercase tracking-wide">STAT / Critical</span>;
      case 'high':
        return <span className="bg-amber-100 text-amber-800 text-[10px] font-bold px-1.5 py-0.5 rounded border border-amber-200 uppercase tracking-wide">High Priority</span>;
      case 'medium':
        return <span className="bg-blue-100 text-blue-700 text-[10px] font-semibold px-1.5 py-0.5 rounded border border-blue-200">Routine Alert</span>;
      default:
        return <span className="bg-slate-100 text-slate-600 text-[10px] font-medium px-1.5 py-0.5 rounded">Info</span>;
    }
  };

  const handleNotificationClick = (notification: AppNotification) => {
    if (!notification.isRead) {
      onMarkAsRead(notification.id);
    }
    if (notification.appointmentId && onSelectAppointment) {
      const matchApt = appointments.find(a => a.id === notification.appointmentId || a.tokenNumber === notification.tokenNumber);
      if (matchApt) {
        onSelectAppointment(matchApt);
      }
    }
    if (notification.targetTab) {
      onNavigateToTab(notification.targetTab, notification.appointmentId);
      onClose();
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-hidden" aria-labelledby="notification-center-title" role="dialog" aria-modal="true">
      {/* Background backdrop */}
      <div
        className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity duration-300"
        onClick={onClose}
      />

      <div className="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div className="w-screen max-w-md md:max-w-lg bg-white shadow-2xl flex flex-col border-l border-slate-200">
          {/* Header */}
          <div className="p-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
            <div className="flex items-center space-x-3">
              <div className="relative p-2 bg-slate-800 rounded-xl border border-slate-700">
                <Bell className="w-5 h-5 text-cyan-400" />
                {unreadCount > 0 && (
                  <span className="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-[10px] font-bold text-white rounded-full flex items-center justify-center border-2 border-slate-900 animate-pulse">
                    {unreadCount > 9 ? '9+' : unreadCount}
                  </span>
                )}
              </div>
              <div>
                <div className="flex items-center space-x-2">
                  <h2 id="notification-center-title" className="text-base font-bold tracking-tight">Clinical Notification Center</h2>
                  {criticalCount > 0 && (
                    <span className="bg-rose-500/20 text-rose-400 border border-rose-500/40 text-[10px] font-black px-1.5 py-0.5 rounded-full animate-pulse flex items-center space-x-1">
                      <Flame className="w-2.5 h-2.5" />
                      <span>{criticalCount} Critical</span>
                    </span>
                  )}
                </div>
                <p className="text-xs text-slate-400 font-medium">Real-time RIS & PACS telemetry feeds</p>
              </div>
            </div>

            <div className="flex items-center space-x-1">
              <button
                onClick={() => setSoundEnabled(!soundEnabled)}
                title={soundEnabled ? 'Mute alert sounds' : 'Enable alert sounds'}
                className="p-1.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-lg transition-colors cursor-pointer"
              >
                {soundEnabled ? <Volume2 className="w-4 h-4 text-cyan-400" /> : <VolumeX className="w-4 h-4 text-slate-500" />}
              </button>
              <button
                onClick={onClose}
                className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Quick Action Bar & Search */}
          <div className="p-3 bg-slate-50 border-b border-slate-200 space-y-2">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
              <input
                type="text"
                placeholder="Search alerts, patients, tokens..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 shadow-xs"
              />
              {searchQuery && (
                <button
                  onClick={() => setSearchQuery('')}
                  className="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs font-bold"
                >
                  ×
                </button>
              )}
            </div>

            {/* Category Filter Pills */}
            <div className="flex items-center space-x-1 overflow-x-auto pb-1 scrollbar-none text-xs">
              <button
                onClick={() => setActiveCategory('all')}
                className={`px-2.5 py-1 rounded-md font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                  activeCategory === 'all'
                    ? 'bg-slate-800 text-white shadow-xs'
                    : 'bg-white text-slate-600 hover:bg-slate-200 border border-slate-200'
                }`}
              >
                All ({notifications.length})
              </button>
              <button
                onClick={() => setActiveCategory('stat')}
                className={`px-2.5 py-1 rounded-md font-semibold whitespace-nowrap transition-colors cursor-pointer flex items-center space-x-1 ${
                  activeCategory === 'stat'
                    ? 'bg-rose-600 text-white shadow-xs'
                    : 'bg-white text-rose-700 hover:bg-rose-50 border border-rose-200'
                }`}
              >
                <Flame className="w-3 h-3" />
                <span>STAT Alerts ({notifications.filter(n => n.category === 'stat' || n.priority === 'critical').length})</span>
              </button>
              <button
                onClick={() => setActiveCategory('workflow')}
                className={`px-2.5 py-1 rounded-md font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                  activeCategory === 'workflow'
                    ? 'bg-cyan-700 text-white shadow-xs'
                    : 'bg-white text-slate-600 hover:bg-slate-200 border border-slate-200'
                }`}
              >
                Workflow ({notifications.filter(n => n.category === 'workflow').length})
              </button>
              <button
                onClick={() => setActiveCategory('billing')}
                className={`px-2.5 py-1 rounded-md font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                  activeCategory === 'billing'
                    ? 'bg-emerald-700 text-white shadow-xs'
                    : 'bg-white text-slate-600 hover:bg-slate-200 border border-slate-200'
                }`}
              >
                Billing ({notifications.filter(n => n.category === 'billing').length})
              </button>
              <button
                onClick={() => setActiveCategory('dispatch')}
                className={`px-2.5 py-1 rounded-md font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                  activeCategory === 'dispatch'
                    ? 'bg-blue-700 text-white shadow-xs'
                    : 'bg-white text-slate-600 hover:bg-slate-200 border border-slate-200'
                }`}
              >
                Doctors ({notifications.filter(n => n.category === 'dispatch').length})
              </button>
              <button
                onClick={() => setActiveCategory('pacs')}
                className={`px-2.5 py-1 rounded-md font-semibold whitespace-nowrap transition-colors cursor-pointer ${
                  activeCategory === 'pacs'
                    ? 'bg-purple-700 text-white shadow-xs'
                    : 'bg-white text-slate-600 hover:bg-slate-200 border border-slate-200'
                }`}
              >
                PACS ({notifications.filter(n => n.category === 'pacs').length})
              </button>
            </div>

            {/* Controls Row */}
            <div className="flex items-center justify-between text-xs pt-1">
              <label className="flex items-center space-x-1.5 cursor-pointer select-none text-slate-600 font-medium">
                <input
                  type="checkbox"
                  checked={onlyUnread}
                  onChange={(e) => setOnlyUnread(e.target.checked)}
                  className="w-3.5 h-3.5 text-cyan-600 rounded border-slate-300 focus:ring-cyan-500 cursor-pointer"
                />
                <span>Unread only ({unreadCount})</span>
              </label>

              <div className="flex items-center space-x-2">
                {unreadCount > 0 && (
                  <button
                    onClick={onMarkAllAsRead}
                    className="text-cyan-700 hover:text-cyan-900 font-semibold flex items-center space-x-1 text-xs hover:underline cursor-pointer"
                  >
                    <CheckCheck className="w-3.5 h-3.5" />
                    <span>Mark all read</span>
                  </button>
                )}
                {notifications.length > 0 && (
                  <button
                    onClick={onClearAll}
                    className="text-slate-500 hover:text-rose-600 font-medium flex items-center space-x-1 text-xs hover:underline cursor-pointer"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                    <span>Clear all</span>
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Notification Items List */}
          <div className="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-2">
            {filteredNotifications.length === 0 ? (
              <div className="text-center py-16 px-4">
                <div className="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400 mb-3">
                  <CheckCircle2 className="w-6 h-6 text-emerald-500" />
                </div>
                <h3 className="text-sm font-bold text-slate-800 mb-1">All Caught Up!</h3>
                <p className="text-xs text-slate-500 max-w-xs mx-auto mb-4">
                  {onlyUnread
                    ? 'No unread alerts matching your current filter criteria.'
                    : 'No notifications logged in this channel yet.'}
                </p>
                {onTriggerTestAlert && (
                  <button
                    onClick={onTriggerTestAlert}
                    className="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-cyan-50 text-cyan-700 border border-cyan-200 rounded-lg text-xs font-semibold hover:bg-cyan-100 cursor-pointer shadow-xs"
                  >
                    <Sparkles className="w-3.5 h-3.5 text-cyan-600" />
                    <span>Send Test STAT Alert</span>
                  </button>
                )}
              </div>
            ) : (
              filteredNotifications.map((notif) => {
                const isCritical = notif.priority === 'critical' || notif.category === 'stat';
                return (
                  <div
                    key={notif.id}
                    className={`p-3.5 rounded-xl border transition-all duration-150 ${
                      !notif.isRead
                        ? isCritical
                          ? 'bg-rose-50/80 border-rose-300 shadow-xs ring-1 ring-rose-200'
                          : 'bg-cyan-50/50 border-cyan-200 shadow-xs'
                        : 'bg-white border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      {/* Icon + Title */}
                      <div className="flex items-start space-x-2.5">
                        <div className={`p-2 rounded-lg shrink-0 mt-0.5 ${
                          isCritical
                            ? 'bg-rose-100 text-rose-700'
                            : notif.category === 'billing'
                            ? 'bg-emerald-100 text-emerald-700'
                            : notif.category === 'dispatch'
                            ? 'bg-blue-100 text-blue-700'
                            : notif.category === 'pacs'
                            ? 'bg-purple-100 text-purple-700'
                            : 'bg-cyan-100 text-cyan-700'
                        }`}>
                          {getCategoryIcon(notif.category, notif.priority)}
                        </div>

                        <div>
                          <div className="flex items-center space-x-1.5 flex-wrap gap-y-1">
                            <h4 className={`text-xs font-bold ${isCritical ? 'text-rose-950' : 'text-slate-900'}`}>
                              {notif.title}
                            </h4>
                            {getPriorityBadge(notif.priority)}
                            {!notif.isRead && (
                              <span className="w-2 h-2 rounded-full bg-cyan-500 shrink-0" title="Unread" />
                            )}
                          </div>

                          <p className="text-xs text-slate-600 mt-1 leading-relaxed">
                            {notif.message}
                          </p>

                          {/* Patient / Token Info Bar */}
                          {(notif.tokenNumber || notif.patientName) && (
                            <div className="flex items-center space-x-2 mt-2 text-[11px] text-slate-500 bg-white/80 py-1 px-2 rounded-md border border-slate-200/60 inline-flex flex-wrap gap-1">
                              {notif.tokenNumber && (
                                <span className="font-mono font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">
                                  #{notif.tokenNumber}
                                </span>
                              )}
                              {notif.patientName && (
                                <span className="font-medium text-slate-800">
                                  {notif.patientName}
                                </span>
                              )}
                            </div>
                          )}

                          {/* Time and Action */}
                          <div className="flex items-center space-x-3 mt-2.5">
                            <div className="flex items-center space-x-1 text-[11px] text-slate-400">
                              <Clock className="w-3 h-3" />
                              <span>{notif.timestamp}</span>
                            </div>

                            {notif.targetTab && (
                              <button
                                onClick={() => handleNotificationClick(notif)}
                                className="inline-flex items-center space-x-1 text-[11px] font-bold text-cyan-700 hover:text-cyan-900 hover:underline cursor-pointer"
                              >
                                <span>{notif.actionLabel || 'View in ' + notif.targetTab}</span>
                                <ExternalLink className="w-3 h-3" />
                              </button>
                            )}
                          </div>
                        </div>
                      </div>

                      {/* Right controls */}
                      <div className="flex items-center space-x-1 shrink-0">
                        {!notif.isRead && (
                          <button
                            onClick={() => onMarkAsRead(notif.id)}
                            title="Mark as read"
                            className="p-1 text-slate-400 hover:text-cyan-700 hover:bg-slate-100 rounded transition-colors cursor-pointer"
                          >
                            <CheckCircle2 className="w-4 h-4" />
                          </button>
                        )}
                        <button
                          onClick={() => onDeleteNotification(notif.id)}
                          title="Delete notification"
                          className="p-1 text-slate-300 hover:text-rose-600 hover:bg-slate-100 rounded transition-colors cursor-pointer"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Footer */}
          <div className="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
            <span className="flex items-center space-x-1">
              <span className="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse" />
              <span>Telemetry Gateway: Connected</span>
            </span>
            {onTriggerTestAlert && (
              <button
                onClick={onTriggerTestAlert}
                className="text-cyan-700 hover:text-cyan-900 font-semibold hover:underline cursor-pointer flex items-center space-x-1"
              >
                <Sparkles className="w-3 h-3" />
                <span>Simulate STAT Alert</span>
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
