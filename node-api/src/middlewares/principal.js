module.exports = function principal() {
  return (req, _res, next) => {
    const roles = (req.header('x-roles') || '')
      .split(',')
      .map(s => s.trim())
      .filter(Boolean);

    req.principal = {
      entityId: Number(req.header('x-entity-id')) || null,
      roles,
      isAdmin: String(req.header('x-admin')).toLowerCase() === 'true',
    };
    next();
  };
};
